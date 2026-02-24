<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\QuantConnectSignal;

class TelegramNotificationService
{
    private ?string $botToken;
    protected ?string $chatId;
    protected ?string $devBotToken;
    protected ?string $devChatId;
    protected string $apiUrl = 'https://api.telegram.org/bot';
    private bool $enabled;

    public function __construct()
    {
        $this->botToken = config('services.telegram.bot_token');
        $this->chatId = config('services.telegram.chat_id');
        $this->devBotToken = config('services.telegram.dev_bot_token');
        $this->devChatId = config('services.telegram.dev_chat_id');
        $this->enabled = config('services.telegram.enabled', false);
    }

    public function sendMessage(string $message, mixed $ids = true, ?string $uniqueLockKey = null): array
    {
        if (!$this->enabled) {
            Log::info('Telegram notifications disabled');
            return ['success' => false, 'message' => 'Telegram disabled'];
        }

        $chatIds = [];
        $botToken = $this->botToken;

        if (is_bool($ids)) {
            $isProduction = $ids;
            $botToken = $isProduction ? $this->botToken : ($this->devBotToken ?: $this->botToken);
            $chatIds[] = $isProduction ? $this->chatId : ($this->devChatId ?: $this->chatId);
        } elseif (is_array($ids)) {
            $chatIds = array_unique(array_filter($ids));
        } elseif ($ids instanceof \Illuminate\Support\Collection) {
            $chatIds = $ids->unique()->filter()->toArray();
        }

        $results = [];
        foreach ($chatIds as $cid) {
            $results[] = $this->sendToSingleChannel($cid, $message, $botToken, $uniqueLockKey);
        }

        return [
            'success' => collect($results)->every('success', true),
            'results' => $results
        ];
    }

    /**
     * Send message to a single Telegram channel with retry and locking logic.
     */
    public function sendToSingleChannel(string $chatId, string $message, ?string $token = null, ?string $uniqueLockKey = null): array
    {
        $botToken = $token ?: $this->botToken;
        
        // [ATOMIC LOCK]: Prevent duplicate sends to the same group for the same signal
        $lockHash = $uniqueLockKey ?: md5($message);
        $cacheKey = "tele_sent_{$chatId}_" . $lockHash;
        
        if (!\Illuminate\Support\Facades\Cache::add($cacheKey, true, 300)) { // 5 menit gembok
            Log::info("Telegram message skipped for {$chatId} (already sent or lock active)");
            return [
                'chat_id' => $chatId,
                'success' => true, // Treat as success to not trigger outer job retry
                'skipped' => true
            ];
        }

        try {
            // [ROBUST]: Use Laravel's HTTP retry for transient network errors (DNS, temp connection loss)
            $response = Http::retry(3, 100)->withOptions([
                'curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]
            ])->timeout(10)->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'Markdown',
            ]);

            if ($response->successful()) {
                return [
                    'chat_id' => $chatId,
                    'success' => true,
                    'response' => $response->json()
                ];
            }

            // [API ERROR]: If Telegram rejected it (e.g. 403 Forbidden, 404 Bot Not in Group)
            // We release the lock so it can be fixed/tried again later if needed, 
            // but we log the error clearly.
            \Illuminate\Support\Facades\Cache::forget($cacheKey);
            Log::error("Telegram API error for {$chatId}: " . $response->body());
            
            return [
                'chat_id' => $chatId,
                'success' => false,
                'error' => 'Telegram API error: ' . $response->body()
            ];

        } catch (\Exception $e) {
            // [TIMEOUT / NETWORK]: 
            // In case of a timeout, we keep the lock for a short duration (e.g. 2 minutes) 
            // to avoid spamming if the message actually reached Telegram but the response timed out.
            // But we don't keep it for 10 minutes like before.
            \Illuminate\Support\Facades\Cache::put($cacheKey, true, 120); 

            Log::error("Telegram send exception for {$chatId}: " . $e->getMessage());
            
            return [
                'chat_id' => $chatId,
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function getUpdates(): array
    {
        try {
            $response = Http::withOptions([
                'curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]
            ])->get("{$this->apiUrl}{$this->botToken}/getUpdates", [
                'limit' => 10,
                'offset' => -10
            ]);
            return $response->json();
        } catch (\Exception $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    public function sendMessageToId(string $chatId, string $message): array
    {
        try {
            // [SUPER FAST]: Retry dipercepat (500ms) & Timeout 15s
            $response = Http::retry(2, 500)->withOptions([
                'curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]
            ])->timeout(15)->post("{$this->apiUrl}{$this->botToken}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'Markdown'
            ]);
            return $response->json();
        } catch (\Exception $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    public function sendNotification(QuantConnectSignal $signal): bool
    {
        if (!$this->enabled) {
            Log::info('Telegram notifications disabled', ['signal_id' => $signal->id]);
            return false;
        }

        $message = $this->formatMessage($signal);

        try {
            // [NO RETRY INTERNAL]
            $response = Http::withOptions([
                'curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]
            ])->timeout(15)->post("https://api.telegram.org/bot{$this->botToken}/sendMessage", [
                'chat_id' => $this->chatId,
                'text' => $message,
                'parse_mode' => 'Markdown',
            ]);

            if ($response->successful()) {
                $signal->update([
                    'telegram_sent' => true,
                    'telegram_sent_at' => now(),
                    'telegram_response' => $response->json(),
                ]);
                Log::info('Telegram notification sent', ['signal_id' => $signal->id, 'type' => $signal->type]);
                return true;
            } else {
                Log::error('Telegram API error', ['signal_id' => $signal->id, 'response' => $response->body()]);
                $signal->update(['telegram_response' => $response->body()]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Telegram notification failed', ['signal_id' => $signal->id, 'error' => $e->getMessage()]);
            $signal->update(['telegram_response' => $e->getMessage()]);
            return false;
        }
    }

    private function formatMessage(QuantConnectSignal $signal): string
    {
        if ($signal->isReminder()) {
            return $this->formatReminderMessage($signal);
        } else {
            return $this->formatSignalMessage($signal);
        }
    }

    private function formatReminderMessage(QuantConnectSignal $signal): string
    {
        $marketEmoji = $signal->isFutures() ? '📊' : '💰';
        $marketType = $signal->market_type;
        return "🔔 *REMINDER* {$marketEmoji}\n\n📌 *Market:* {$marketType}\n🪙 *Symbol:* `{$signal->symbol}`\n💬 *Message:* {$signal->message}\n\n⏰ " . now()->format('Y-m-d H:i:s') . " WIB\n🤖 QC ID: `{$signal->qc_id}`";
    }

    private function formatSignalMessage(QuantConnectSignal $signal): string
    {
        $sideEmoji = $signal->side === 'BUY' ? '📈' : '📉';
        $marketEmoji = $signal->isFutures() ? '📊' : '💰';
        $marketType = $signal->market_type;

        $message = "{$sideEmoji} *{$signal->side} SIGNAL* {$marketEmoji}\n\n📌 *Market:* {$marketType}\n🪙 *Symbol:* `{$signal->symbol}`\n💵 *Entry Price:* `" . number_format($signal->price, 2) . "`\n🎯 *Take Profit:* `" . number_format($signal->tp, 2) . "`\n🛡️ *Stop Loss:* `" . number_format($signal->sl, 2) . "`\n";

        if ($signal->isFutures() && $signal->leverage) $message .= "⚡ *Leverage:* `{$signal->leverage}x`\n";
        if ($signal->margin_usd) $message .= "💼 *Margin:* `$" . number_format($signal->margin_usd, 2) . "`\n";
        if ($signal->quantity) $message .= "📊 *Quantity:* `{$signal->quantity}`\n";

        $message .= "\n💬 *Message:* {$signal->message}\n\n⏰ " . now()->format('Y-m-d H:i:s') . " WIB\n🤖 QC ID: `{$signal->qc_id}`";
        return $message;
    }
}
