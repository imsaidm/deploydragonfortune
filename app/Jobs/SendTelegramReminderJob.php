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
        try {
            // Format message
            $message = "⚠️ *REMINDER*\n\n";
            $message .= "📝 {$this->reminder->message}\n";
            $message .= "\n⏰ " . now()->format('Y-m-d H:i:s') . " WIB";
            
            // Send to Telegram
            $response = $telegram->sendMessage($message);
            
            // Update status
            $this->reminder->update([
                'telegram_sent' => true,
                'telegram_sent_at' => now(),
                'telegram_response' => json_encode($response)
            ]);
            
            Log::info("✅ Reminder #{$this->reminder->id} sent to Telegram");
            
        } catch (\Exception $e) {
            Log::error("❌ Reminder #{$this->reminder->id} failed: {$e->getMessage()}");
            
            if ($this->attempts() < $this->tries) {
                $this->release($this->backoff[$this->attempts() - 1] ?? 60);
            } else {
                $this->reminder->update([
                    'telegram_response' => 'Failed after ' . $this->tries . ' attempts: ' . $e->getMessage()
                ]);
            }
            
            throw $e;
        }
    }
}
