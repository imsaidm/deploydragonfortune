<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\QuantConnectSignal;

class TelegramNotificationService
{
    private ?string $botToken;
    private ?string $chatId;
    private ?string $devBotToken;
    private ?string $devChatId;
    private bool $enabled;

    public function __construct()
    {
        $this->botToken = config('services.telegram.bot_token');
        $this->chatId = config('services.telegram.chat_id');
        $this->devBotToken = config('services.telegram.dev_bot_token');
        $this->devChatId = config('services.telegram.dev_chat_id');
        $this->enabled = config('services.telegram.enabled', false);
    }

    /**
     * Send a generic message to Telegram
     * * @param string $message
     * @param bool|array|null $ids If bool, use production/dev from config. If array/collection, use those chat IDs.
     * @return array
     */
    public function sendMessage(string $message, mixed $ids = true): array
    {
        if (!$this->enabled) {
            Log::info('Telegram notifications disabled');
            return ['success' => false, 'message' => 'Telegram disabled'];
        }

        $chatIds = [];
        $botToken = $this->botToken; // Default to production bot

        if (is_bool($ids)) {
            // Legacy behavior: use config based on isProduction
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
            try {
                // [TAMBAHAN SAYA] Kasih jeda 0.5 detik (500ms) tiap kirim ke grup baru biar Telegram gak marah (Anti-Spam)
                usleep(500000);

                // [TAMBAHAN SAYA] Pasang pengaman Timeout 15 detik, dan kalau gagal coba ulang 3 kali (jeda 2 detik)
                $response = Http::timeout(15)
                    ->retry(3, 2000)
                    ->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                    'chat_id' => $cid,
                    'text' => $message,
                    'parse_mode' => 'Markdown',
                ]);

                if ($response->successful()) {
                    $results[] = [
                        'chat_id' => $cid,
                        'success' => true,
                        'response' => $response->json()
                    ];
                } else {
                    $results[] = [
                        'chat_id' => $cid,
                        'success' => false,
                        'error' => 'Telegram API error: ' . $response->body()
                    ];
                    Log::error("Telegram API error for {$cid}: " . $response->body());
                }
            } catch (\Exception $e) {
                $results[] = [
                    'chat_id' => $cid,
                    'success' => false,
                    'error' => $e->getMessage()
                ];
                Log::error("Telegram send failed for {$cid}: " . $e->getMessage());
            }
        }

        return [
            'success' => collect($results)->every('success', true),
            'results' => $results
        ];
    }

    /**
     * Get recent updates from the bot.
     */
    public function getUpdates(): array
    {
        try {
            $response = Http::get("{$this->apiUrl}{$this->botToken}/getUpdates", [
                'limit' => 10,
                'offset' => -10
            ]);

            return $response->json();
        } catch (\Exception $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send specific message to a chat id.
     */
    public function sendMessageToId(string $chatId, string $message): array
    {
        try {
            // [TAMBAHAN SAYA] Dikasih retry juga buat jaga-jaga
            $response = Http::timeout(15)
                ->retry(3, 2000)
                ->post("{$this->apiUrl}{$this->botToken}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'Markdown'
            ]);

            return $response->json();
        } catch (\Exception $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send notification to Telegram
     */
    public function sendNotification(QuantConnectSignal $signal): bool
    {
        if (!$this->enabled) {
            Log::info('Telegram notifications disabled', ['signal_id' => $signal->id]);
            return false;
        }

        $message = $this->formatMessage($signal);
        
        try {
            // [TAMBAHAN SAYA] Dikasih retry juga biar kebal
            $response = Http::timeout(15)
                ->retry(3, 2000)
                ->post("https://api.telegram.org/bot{$this->botToken}/sendMessage", [
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

                Log::info('Telegram notification sent', [
                    'signal_id' => $signal->id,
                    'type' => $signal->type,
                ]);

                return true;
            } else {
                Log::error('Telegram API error', [
                    'signal_id' => $signal->id,
                    'response' => $response->body(),
                ]);

                $signal->update([
                    'telegram_response' => $response->body(),
                ]);

                return false;
            }
        } catch (\Exception $e) {
            Log::error('Telegram notification failed', [
                'signal_id' => $signal->id,
                'error' => $e->getMessage(),
            ]);

            $signal->update([
                'telegram_response' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Format message based on signal type
     */
    private function formatMessage(QuantConnectSignal $signal): string
    {
        if ($signal->isReminder()) {
            return $this->formatReminderMessage($signal);
        } else {
            return $this->formatSignalMessage($signal);
        }
    }

    /**
     * Format reminder message
     */
    private function formatReminderMessage(QuantConnectSignal $signal): string
    {
        $marketEmoji = $signal->isFutures() ? '📊' : '💰';
        $marketType = $signal->market_type;
        
        return "🔔 *REMINDER* {$marketEmoji}\n\n"
            . "📌 *Market:* {$marketType}\n"
            . "🪙 *Symbol:* `{$signal->symbol}`\n"
            . "💬 *Message:* {$signal->message}\n\n"
            . "⏰ " . now()->format('Y-m-d H:i:s') . " WIB\n"
            . "🤖 QC ID: `{$signal->qc_id}`";
    }

    /**
     * Format signal message
     */
    private function formatSignalMessage(QuantConnectSignal $signal): string
    {
        $sideEmoji = $signal->side === 'BUY' ? '📈' : '📉';
        $marketEmoji = $signal->isFutures() ? '📊' : '💰';
        $marketType = $signal->market_type;
        
        $message = "{$sideEmoji} *{$signal->side} SIGNAL* {$marketEmoji}\n\n"
            . "📌 *Market:* {$marketType}\n"
            . "🪙 *Symbol:* `{$signal->symbol}`\n"
            . "💵 *Entry Price:* `" . number_format($signal->price, 2) . "`\n"
            . "🎯 *Take Profit:* `" . number_format($signal->tp, 2) . "`\n"
            . "🛡️ *Stop Loss:* `" . number_format($signal->sl, 2) . "`\n";

        // Add futures-specific info
        if ($signal->isFutures() && $signal->leverage) {
            $message .= "⚡ *Leverage:* `{$signal->leverage}x`\n";
        }

        if ($signal->margin_usd) {
            $message .= "💼 *Margin:* `$" . number_format($signal->margin_usd, 2) . "`\n";
        }

        if ($signal->quantity) {
            $message .= "📊 *Quantity:* `{$signal->quantity}`\n";
        }

        $message .= "\n💬 *Message:* {$signal->message}\n\n"
            . "⏰ " . now()->format('Y-m-d H:i:s') . " WIB\n"
            . "🤖 QC ID: `{$signal->qc_id}`";

        return $message;
    }
}