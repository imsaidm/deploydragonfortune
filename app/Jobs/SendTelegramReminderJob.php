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
        public QcReminder $reminder
    ) {}

    /**
     * Execute the job.
     */
    public function handle(TelegramNotificationService $telegram): void
    {
        // [ANTI-SPAM 100% MUTLAK]: Jika sudah terkirim oleh klonengan apa pun, segera batalkan.
        if ($this->reminder->telegram_sent) {
            Log::info("Job aborted: Reminder #{$this->reminder->id} was already marked as sent in DB.");
            return;
        }

        // [LOCK AKTIF]: Pakai nama kunci beda ('active_') biar gak bentrok sama Scheduler ('dispatch_')
        $lockKey = 'active_tele_reminder_' . $this->reminder->id;
        if (!\Illuminate\Support\Facades\Cache::add($lockKey, true, 300)) {
            Log::info("Job skipped: Reminder #{$this->reminder->id} is already being executed.");
            return;
        }

        try {
            // Load method relationship
            $this->reminder->load('method');
            $method = $this->reminder->method;

            // Build professional reminder message
            $message = "━━━━━━━━━━━━━━━━━━━━━━\n";
            $message .= "🔔 *DRAGONFORTUNE REMINDER*\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━━━\n\n";

            // Method Information (if available)
            if ($method) {
                // Bersihkan string yang mungkin ada underscore atau asterisk-nya (agar tidak crash parser Markdown Telegram)
                $safeName = str_replace(['_', '*', '`', '[', ']'], ' ', $method->nama_metode);
                $safeCreator = str_replace(['_', '*', '`', '[', ']'], ' ', $method->creator);

                $message .= "📊 *Strategy Info*\n";
                $message .= "├ Name: `{$safeName}`\n";
                $message .= "├ Creator: `{$safeCreator}`\n";
                $message .= "├ Exchange: `{$method->exchange}`\n";
                $message .= "├ Pair: `{$method->pair}`\n";
                $message .= "└ Timeframe: `{$method->tf}`\n\n";

                // Key Performance Metrics
                $message .= "📈 *Performance Metrics*\n";
                $message .= "├ CAGR: `" . number_format($method->cagr, 2) . "%`\n";
                $message .= "├ Max Drawdown: `" . number_format($method->drawdown, 2) . "%`\n";
                $message .= "├ Winrate: `" . number_format($method->winrate, 1) . "%` ";
                $message .= "| Lossrate: `" . number_format($method->lossrate, 1) . "%`\n";
                $message .= "├ Sharpe Ratio: `" . number_format($method->sharpen_ratio, 3) . "`\n";
                $message .= "├ Sortino Ratio: `" . number_format($method->sortino_ratio, 3) . "`\n";
                $message .= "├ Info Ratio: `" . number_format($method->information_ratio, 3) . "`\n";
                $message .= "├ Prob SR: `" . number_format($method->prob_sr, 2) . "%`\n";
                $message .= "└ Total Orders: `" . number_format($method->total_orders, 0) . "`\n\n";

                // Status indicator
                $statusEmoji = $method->onactive ? '🟢' : '🔴';
                $statusText = $method->onactive ? 'Active' : 'Inactive';
                $message .= "⚡ *Status*: {$statusEmoji} `{$statusText}`\n\n";
            }

            // Reminder Message
            $message .= "━━━━━━━━━━━━━━━━━━━━━━\n";
            $message .= "📝 *Message*\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━━━\n\n";

            // Bersihkan pesan dari Markdown yang menggantung (Can't parse entities error)
            $safeMessage = str_replace(['_', '*', '`', '[', ']'], ' ', $this->reminder->message);
            $message .= "{$safeMessage}\n\n";

            // Footer
            $message .= "━━━━━━━━━━━━━━━━━━━━━━\n";
            $message .= "⏰ " . now()->setTimezone('Asia/Jakarta')->format('d M Y, H:i:s') . " WIB\n";
            $message .= "🤖 _Powered by DragonFortune AI_\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━━━";

            $this->reminder->load('method.telegramChannels');
            $method = $this->reminder->method;

            $chatIds = [];
            if ($method && $method->telegramChannels->count() > 0) {
                $chatIds = $method->telegramChannels->where('is_active', true)->pluck('chat_id')->toArray();
            }

            // Send to Telegram (selective or fallback)
            $uniqueLockKey = "unique_reminder_" . $this->reminder->id;

            if (empty($chatIds)) {
                $isProduction = $method ? (bool) $method->is_production : false;
                $response = $telegram->sendMessage($message, $isProduction, $uniqueLockKey);
            } else {
                $response = $telegram->sendMessage($message, $chatIds, $uniqueLockKey);
            }

            // [VITAL]: Memancing Retry Worker dari Laravel jika ada grup yang gagal
            if (isset($response['success']) && !$response['success']) {
                throw new \Exception("Beberapa notifikasi reminder gagal terkirim. Cek log Telegram error.");
            }

            // Update status
            $this->reminder->update([
                'telegram_sent' => true,
                'telegram_sent_at' => now(),
                'telegram_response' => json_encode($response)
            ]);

            // Lepas gembok
            \Illuminate\Support\Facades\Cache::forget($lockKey);
            \Illuminate\Support\Facades\Cache::forget('dispatch_tele_reminder_' . $this->reminder->id);

            Log::info("✅ Reminder #{$this->reminder->id} sent to Telegram");
        } catch (\Exception $e) {
            Log::error("❌ Reminder #{$this->reminder->id} failed: {$e->getMessage()}");

            // Lepas CUMA gembok aktif biar job ini bisa di-retry oleh Worker.
            // JANGAN LEPAS gembok dispatch_!
            try {
                \Illuminate\Support\Facades\Cache::forget('active_tele_reminder_' . $this->reminder->id);
            } catch (\Throwable $t) {
            }

            if ($this->attempts() < $this->tries) {
                $this->release($this->backoff[$this->attempts() - 1] ?? 60);
                return; // [HENTIKAN DISINI]: Mencegah Laravel membuat Job Retry ganda!
            } else {
                try {
                    \Illuminate\Support\Facades\Cache::forget('dispatch_tele_reminder_' . $this->reminder->id);
                } catch (\Throwable $t) {
                }
                $this->reminder->update([
                    'telegram_response' => 'Failed after ' . $this->tries . ' attempts: ' . $e->getMessage()
                ]);
            }
            throw $e;
        }
    }
}
