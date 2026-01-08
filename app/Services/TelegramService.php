<?php

namespace App\Services;

use App\Models\QuantconnectSignal;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    private string $botToken;
    private string $chatId;
    private string $baseUrl;
    private int $timeoutSeconds;
    private int $maxRetries;
    private string $timezone = 'Asia/Jakarta'; // WIB (UTC+7)

    public function __construct(
        ?string $botToken = null,
        ?string $chatId = null,
        ?int $timeoutSeconds = null,
        ?int $maxRetries = null
    ) {
        $this->botToken = $botToken ?? (string) config('services.telegram.bot_token', '');
        $this->chatId = $chatId ?? (string) config('services.telegram.chat_id', '');
        $this->baseUrl = 'https://api.telegram.org/bot' . $this->botToken;
        $this->timeoutSeconds = $timeoutSeconds ?? (int) config('services.telegram.timeout', 10);
        $this->maxRetries = $maxRetries ?? (int) config('services.telegram.max_retries', 3);
    }

    /**
     * Check if Telegram is properly configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->botToken) && !empty($this->chatId);
    }

    /**
     * Send a signal notification to Telegram
     */
    public function sendSignalNotification(QuantconnectSignal $signal): bool
    {
        if (!$this->isConfigured()) {
            Log::warning('Telegram not configured, skipping notification', [
                'signal_id' => $signal->id
            ]);
            return false;
        }

        $message = $this->formatMessage($signal);

        return $this->sendMessage($message);
    }

    /**
     * Send a message to Telegram with retry logic
     */
    public function sendMessage(string $message, ?string $chatId = null): bool
    {
        if (!$this->isConfigured()) {
            Log::warning('Telegram not configured, cannot send message');
            return false;
        }

        $targetChatId = $chatId ?? $this->chatId;
        $attempt = 0;

        while ($attempt < $this->maxRetries) {
            $attempt++;

            try {
                $response = $this->makeApiCall('sendMessage', [
                    'chat_id' => $targetChatId,
                    'text' => $message,
                    'parse_mode' => 'Markdown',
                    'disable_web_page_preview' => true
                ]);

                if ($response['success']) {
                    Log::info('Telegram message sent successfully', [
                        'chat_id' => $targetChatId,
                        'attempt' => $attempt
                    ]);
                    return true;
                }

                Log::warning('Telegram API returned error', [
                    'chat_id' => $targetChatId,
                    'attempt' => $attempt,
                    'error' => $response['error'] ?? 'Unknown error'
                ]);
            } catch (\Exception $e) {
                Log::error('Telegram API request failed', [
                    'chat_id' => $targetChatId,
                    'attempt' => $attempt,
                    'error' => $e->getMessage()
                ]);
            }

            // Wait before retry (exponential backoff)
            if ($attempt < $this->maxRetries) {
                sleep(pow(2, $attempt - 1)); // 1s, 2s, 4s...
            }
        }

        Log::error('Failed to send Telegram message after all retries', [
            'chat_id' => $targetChatId,
            'max_retries' => $this->maxRetries
        ]);

        return false;
    }

    /**
     * Test the Telegram bot connection
     */
    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'Telegram bot token or chat ID not configured'
            ];
        }

        try {
            $response = $this->makeApiCall('getMe');

            if ($response['success']) {
                return [
                    'success' => true,
                    'bot_info' => $response['data']['result'] ?? null
                ];
            }

            return [
                'success' => false,
                'error' => $response['error'] ?? 'Unknown error'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get chat information
     */
    public function getChatInfo(?string $chatId = null): array
    {
        $targetChatId = $chatId ?? $this->chatId;

        try {
            $response = $this->makeApiCall('getChat', [
                'chat_id' => $targetChatId
            ]);

            if ($response['success']) {
                return [
                    'success' => true,
                    'chat_info' => $response['data']['result'] ?? null
                ];
            }

            return [
                'success' => false,
                'error' => $response['error'] ?? 'Unknown error'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Format signal data into a Telegram message
     */
    private function formatMessage(QuantconnectSignal $signal): string
    {
        // Use different format for ALERT type
        if ($signal->signal_type === 'alert') {
            return $this->formatAlertMessage($signal);
        }

        $emoji = $this->getEmojiForSignal($signal);
        $action = strtoupper($signal->action);
        $type = ucfirst($signal->signal_type);

        $message = "{$emoji} *SIGNAL ALERT* {$emoji}\n\n";
        $message .= "📊 *Project*: {$signal->project_name}\n";
        $message .= "💰 *Symbol*: {$signal->symbol}\n";
        $message .= "📈 *Action*: {$action} ({$type})\n";
        $message .= "💵 *Price*: $" . number_format($signal->price, 2) . "\n";

        if ($signal->quantity) {
            $message .= "📦 *Quantity*: " . number_format($signal->quantity, 4) . "\n";
        }

        if ($signal->target_price) {
            $message .= "🎯 *Target*: $" . number_format($signal->target_price, 2) . "\n";
        }

        if ($signal->stop_loss) {
            $message .= "🛡️ *Stop Loss*: $" . number_format($signal->stop_loss, 2) . "\n";
        }

        if ($signal->realized_pnl !== null) {
            $pnlEmoji = $signal->realized_pnl >= 0 ? '💰' : '📉';
            $pnlSign = $signal->realized_pnl >= 0 ? '+' : '';
            $message .= "{$pnlEmoji} *P&L*: {$pnlSign}$" . number_format($signal->realized_pnl, 2) . "\n";
        }

        if ($signal->message) {
            $message .= "\n📝 *Message*: " . $this->escapeMarkdown($signal->message) . "\n";
        }

        $message .= "\n⏰ *Time*: " . $this->formatTimeWIB($signal->signal_timestamp);

        return $message;
    }

    /**
     * Format ALERT/Reminder message (different style from actual signals)
     */
    private function formatAlertMessage(QuantconnectSignal $signal): string
    {
        $action = strtoupper($signal->action);
        $actionEmoji = in_array($signal->action, ['buy', 'long']) ? '🟢' : '🔴';

        $message = "⚠️ *HEADS UP \\- SIGNAL INCOMING* ⚠️\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━\n\n";

        $message .= "📊 *{$signal->symbol}* preparing for *{$action}*\n\n";

        $message .= "{$actionEmoji} *Expected Setup:*\n";
        $message .= "• Entry Zone: \\~$" . number_format($signal->price, 2) . "\n";

        if ($signal->target_price) {
            $message .= "• Target TP: $" . number_format($signal->target_price, 2) . "\n";
        }

        if ($signal->stop_loss) {
            $message .= "• Stop Loss: $" . number_format($signal->stop_loss, 2) . "\n";
        }

        // Calculate potential risk/reward if both TP and SL are set
        if ($signal->target_price && $signal->stop_loss && $signal->price > 0) {
            $reward = abs($signal->target_price - $signal->price);
            $risk = abs($signal->price - $signal->stop_loss);
            if ($risk > 0) {
                $rrRatio = round($reward / $risk, 2);
                $message .= "• Risk/Reward: 1:{$rrRatio}\n";
            }
        }

        if ($signal->message) {
            $message .= "\n💡 *Analysis:*\n" . $this->escapeMarkdown($signal->message) . "\n";
        }

        $message .= "\n🔔 *Actual signal will follow shortly\\!*\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "⏰ " . $this->formatTimeWIB($signal->signal_timestamp, 'H:i:s') . " WIB";

        return $message;
    }

    /**
     * Get appropriate emoji for signal type and action
     */
    private function getEmojiForSignal(QuantconnectSignal $signal): string
    {
        return match ($signal->signal_type) {
            'entry' => match ($signal->action) {
                'buy', 'long' => '🚀',
                'sell', 'short' => '📉',
                default => '📊'
            },
            'exit' => '🏁',
            'alert' => '⚠️',
            'update' => '🔄',
            'error' => '❌',
            default => '📊'
        };
    }

    /**
     * Escape special characters for Telegram Markdown
     */
    private function escapeMarkdown(string $text): string
    {
        // Escape special Markdown characters
        $specialChars = ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'];

        foreach ($specialChars as $char) {
            $text = str_replace($char, '\\' . $char, $text);
        }

        return $text;
    }

    /**
     * Make an API call to Telegram
     */
    private function makeApiCall(string $method, array $params = []): array
    {
        $url = $this->baseUrl . '/' . $method;

        try {
            $response = Http::timeout($this->timeoutSeconds)
                ->acceptJson()
                ->withOptions([
                    'verify' => app()->environment('production')
                ])
                ->post($url, $params);

            $data = $response->json();

            if ($response->successful() && isset($data['ok']) && $data['ok'] === true) {
                return [
                    'success' => true,
                    'data' => $data
                ];
            }

            $errorMessage = $data['description'] ?? 'Unknown Telegram API error';

            return [
                'success' => false,
                'error' => $errorMessage,
                'error_code' => $data['error_code'] ?? null
            ];
        } catch (\Exception $e) {
            throw new \Exception('Telegram API request failed: ' . $e->getMessage());
        }
    }

    /**
     * Send a test message to verify configuration
     */
    public function sendTestMessage(): bool
    {
        $message = "🤖 *Test Message*\n\n";
        $message .= "This is a test message from the QuantConnect Signal Manager.\n";
        $message .= "If you receive this, your Telegram integration is working correctly!\n\n";
        $message .= "⏰ *Time*: " . $this->formatTimeWIB(now());

        return $this->sendMessage($message);
    }

    /**
     * Send a formatted error notification
     */
    public function sendErrorNotification(string $error, array $context = []): bool
    {
        $message = "⚠️ *ERROR ALERT* ⚠️\n\n";
        $message .= "🔴 *Error*: " . $this->escapeMarkdown($error) . "\n";

        if (!empty($context)) {
            $message .= "\n📋 *Context*:\n";
            foreach ($context as $key => $value) {
                $key = $this->escapeMarkdown((string) $key);
                $value = $this->escapeMarkdown((string) $value);
                $message .= "• *{$key}*: {$value}\n";
            }
        }

        $message .= "\n⏰ *Time*: " . $this->formatTimeWIB(now());

        return $this->sendMessage($message);
    }

    /**
     * Send a system status notification
     */
    public function sendStatusNotification(string $status, array $details = []): bool
    {
        $emoji = match (strtolower($status)) {
            'online', 'active', 'running' => '🟢',
            'offline', 'stopped', 'inactive' => '🔴',
            'warning', 'degraded' => '🟡',
            default => '🔵'
        };

        $message = "{$emoji} *SYSTEM STATUS* {$emoji}\n\n";
        $message .= "📊 *Status*: " . strtoupper($status) . "\n";

        if (!empty($details)) {
            $message .= "\n📋 *Details*:\n";
            foreach ($details as $key => $value) {
                $key = $this->escapeMarkdown((string) $key);
                $value = $this->escapeMarkdown((string) $value);
                $message .= "• *{$key}*: {$value}\n";
            }
        }

        $message .= "\n⏰ *Time*: " . $this->formatTimeWIB(now());

        return $this->sendMessage($message);
    }

    /**
     * Format datetime to WIB (Waktu Indonesia Barat, UTC+7)
     */
    private function formatTimeWIB($datetime, string $format = 'Y-m-d H:i:s'): string
    {
        if ($datetime === null) {
            return '-';
        }

        $carbon = $datetime instanceof \Carbon\Carbon
            ? $datetime
            : \Carbon\Carbon::parse($datetime);

        return $carbon->setTimezone($this->timezone)->format($format) . ' WIB';
    }
}
