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
     * Send message to a single Telegram channel with robust locking logic.
     */
    public function sendToSingleChannel(string $chatId, string $message, ?string $token = null, ?string $uniqueLockKey = null): array
    {
        $botToken = $token ?: $this->botToken;
        
        // [UNIQUE KEY]: Use fixed key from DB ID to stay consistent across Job retries
        $lockHash = $uniqueLockKey ?: md5($message);
        $sentKey = "tele_sent_{$chatId}_{$lockHash}";
        $procKey = "tele_proc_{$chatId}_{$lockHash}";

        // 1. [ALREADY SENT]: If this group already received this signal, stop (Permanent-ish lock)
        if (\Illuminate\Support\Facades\Cache::has($sentKey)) {
            return [
                'chat_id' => $chatId,
                'success' => true,
                'skipped' => true
            ];
        }

        // 2. [ATOMIC PROCESSING LOCK]: Avoid 2 workers sending same message at once
        // Durasi pendek (2 menit) biar kalau Job macet, gemboknya lepas sendiri.
        if (!\Illuminate\Support\Facades\Cache::add($procKey, true, 120)) {
            Log::info("Telegram send skipped: Processing lock active for {$chatId}");
            return [
                'chat_id' => $chatId,
                'success' => false,
                'error' => 'Processing lock active'
            ];
        }

        try {
            // [JITTER]: Small delay to spread out burst requests to Telegram API
            usleep(rand(100000, 300000)); // 100-300ms

            // [DIRECT SEND]: No internal retry. Let Laravel Job handle retries with backoff.
            // Timeout 12s (standard for Telegram API latency)
            $response = Http::withOptions([
                'curl' => [
                    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                    CURLOPT_DNS_CACHE_TIMEOUT => 300
                ]
            ])->timeout(12)->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'Markdown',
            ]);

            if ($response->successful()) {
                // [SUCCESS]: Mark as SENT for 1 hour (long enough for any Job retries to finish)
                \Illuminate\Support\Facades\Cache::put($sentKey, true, 3600);
                \Illuminate\Support\Facades\Cache::forget($procKey);

                return [
                    'chat_id' => $chatId,
                    'success' => true,
                    'response' => $response->json()
                ];
            }

            // [API ERROR]: e.g. 429 Too Many Requests, 403 Forbidden
            \Illuminate\Support\Facades\Cache::forget($procKey);
            Log::error("Telegram API error for {$chatId}: " . $response->body());
            
            return [
                'chat_id' => $chatId,
                'success' => false,
                'error' => 'Telegram API error: ' . $response->body()
            ];

        } catch (\Exception $e) {
            // [TIMEOUT / NETWORK]:
            // SANGAT PENTING: Jangan tandai sebagai 'sent'! Lepas gembok 'proc'
            // agar Laravel Job Retry bisa mencoba lagi di iterasi berikutnya.
            \Illuminate\Support\Facades\Cache::forget($procKey);

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
