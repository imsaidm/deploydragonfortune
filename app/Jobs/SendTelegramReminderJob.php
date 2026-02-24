<?php

namespace App\Jobs;

use App\Models\QcReminder;
use App\Services\TelegramNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendTelegramReminderJob implements ShouldQueue
{
    use Queueable;

    public $tries = 3;
    public $backoff = [10, 30, 60];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public QcReminder $reminder,
        public ?string $chatId = null
    ) {}

    public function handle(TelegramNotificationService $telegram): void
    {
        // 1. FAN-OUT MODE (Dispatcher)
        if ($this->chatId === null) {
            $this->fanOutToChannels($telegram);
            return;
        }

        // 2. SENDER MODE (Single Target)
        $this->processSingleTarget($telegram);
    }

    /**
     * Dispatch separate jobs for each target channel to ensure isolation.
     */
    private function fanOutToChannels(TelegramNotificationService $telegram): void
    {
        // [ANTI-SPAM]: If already sent globally, stop.
        if ($this->reminder->telegram_sent) {
            return;
        }

        $this->reminder->load('method.telegramChannels');
        $method = $this->reminder->method;

        $chatIds = [];
        if ($method && $method->telegramChannels->count() > 0) {
            $chatIds = $method->telegramChannels->where('is_active', true)->pluck('chat_id')->toArray();
        }

        // Fallback
        if (empty($chatIds)) {
            $isProduction = $method ? (bool) $method->is_production : false;
            $chatIds = $isProduction ? [config('services.telegram.chat_id')] : [config('services.telegram.dev_chat_id') ?: config('services.telegram.chat_id')];
        }

        // 1. Mark as processing
        $this->reminder->update(['telegram_processing' => true]);

        $chatIds = array_filter(array_unique($chatIds));

        // 2. Mark as sent globally BEFORE dispatching
        $this->reminder->update([
            'telegram_sent' => true,
            'telegram_sent_at' => now(),
            'telegram_processing' => false
        ]);

        foreach ($chatIds as $index => $cid) {
            // Delay bertahap + jitter
            $delaySeconds = ($index * 3) + rand(0, 2);
            SendTelegramReminderJob::dispatch($this->reminder, $cid)->delay(now()->addSeconds($delaySeconds));
        }

        \Illuminate\Support\Facades\Cache::forget('dispatch_tele_reminder_' . $this->reminder->id);
    }

    /**
     * Process sending to a specific chat ID.
     */
    private function processSingleTarget(TelegramNotificationService $telegram): void
    {
        $lockKey = 'active_tele_rem_job_' . $this->reminder->id . '_' . $this->chatId;
        
        if (!\Illuminate\Support\Facades\Cache::add($lockKey, true, 300)) {
            return;
        }

        try {
            $this->reminder->load('method');
            $method = $this->reminder->method;

            // Build message (copy-pasted logic from original but cleaned)
            $message = "━━━━━━━━━━━━━━━━━━━━━━\n";
            $message .= "🔔 *DRAGONFORTUNE REMINDER*\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━━━\n\n";

            if ($method) {
                $safeName = str_replace(['_', '*', '`', '[', ']'], ' ', $method->nama_metode);
                $safeCreator = str_replace(['_', '*', '`', '[', ']'], ' ', $method->creator);

                $message .= "📊 *Strategy Info*\n";
                $message .= "├ Name: `{$safeName}`\n";
                $message .= "├ Creator: `{$safeCreator}`\n";
                $message .= "├ Exchange: `{$method->exchange}`\n";
                $message .= "├ Pair: `{$method->pair}`\n";
                $message .= "└ Timeframe: `{$method->tf}`\n\n";

                $message .= "📈 *Performance Metrics*\n";
                $message .= "├ CAGR: `" . number_format($method->cagr, 2) . "%`\n";
                $message .= "├ Max DD: `" . number_format($method->drawdown, 2) . "%`\n";
                $message .= "├ Winrate: `" . number_format($method->winrate, 1) . "%`\n";
                $message .= "├ Sharpe: `" . number_format($method->sharpen_ratio, 3) . "`\n";
                $message .= "├ Sortino: `" . number_format($method->sortino_ratio, 3) . "`\n";
                $message .= "└ Total Trades: `" . number_format($method->total_orders, 0) . "`\n\n";

                $statusEmoji = $method->onactive ? '🟢' : '🔴';
                $statusText = $method->onactive ? 'Active' : 'Inactive';
                $message .= "⚡ *Status*: {$statusEmoji} `{$statusText}`\n\n";
            }

            $message .= "━━━━━━━━━━━━━━━━━━━━━━\n";
            $message .= "📝 *Message*\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━━━\n\n";
            $safeMsgBody = str_replace(['_', '*', '`', '[', ']'], ' ', $this->reminder->message);
            $message .= "{$safeMsgBody}\n\n";

            $message .= "━━━━━━━━━━━━━━━━━━━━━━\n";
            $message .= "⏰ " . now()->setTimezone('Asia/Jakarta')->format('d M Y, H:i:s') . " WIB\n";
            $message .= "🤖 _Powered by DragonFortune AI_\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━━━";

            $uniqueLockKey = "unique_rem_{$this->reminder->id}";
            $token = null;

            if ($method && $this->chatId === config('services.telegram.dev_chat_id')) {
                $token = config('services.telegram.dev_bot_token');
            }

            $response = $telegram->sendToSingleChannel($this->chatId, $message, $token, $uniqueLockKey);

            if (!$response['success'] && !isset($response['skipped'])) {
                throw new \Exception("Telegram reminder failed for {$this->chatId}: " . ($response['error'] ?? 'Unknown error'));
            }

            \Illuminate\Support\Facades\Cache::forget($lockKey);

        } catch (\Exception $e) {
            Log::error("❌ Reminder #{$this->reminder->id} for Group {$this->chatId} failed: {$e->getMessage()}");
            \Illuminate\Support\Facades\Cache::forget($lockKey);
            throw $e;
        }
    }
}
