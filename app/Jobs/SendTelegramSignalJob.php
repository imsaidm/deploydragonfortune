<?php

namespace App\Jobs;

use App\Models\QcSignal;
use App\Services\TelegramNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendTelegramSignalJob implements ShouldQueue
{
    use Queueable;

    public $tries = 3;
    public $backoff = [10, 30, 60]; // Retry after 10s, 30s, 60s

    /**
     * Create a new job instance.
     */
    public function __construct(
        public QcSignal $signal
    ) {}

    /**
     * Execute the job.
     */
    public function handle(TelegramNotificationService $telegram): void
    {
        try {
            // Format message
            $message = "🚀 *SIGNAL TRADING*\n\n";
            $message .= "📊 Symbol: `{$this->signal->jenis}`\n";
            $message .= "📈 Type: *{$this->signal->type}*\n";
            $message .= "💰 Entry: `" . number_format($this->signal->price_entry, 2) . "`\n";
            $message .= "🎯 TP: `" . number_format($this->signal->target_tp, 2) . "`\n";
            $message .= "🛑 SL: `" . number_format($this->signal->target_sl, 2) . "`\n";
            $message .= "\n📝 {$this->signal->message}\n";
            $message .= "\n⏰ " . now()->format('Y-m-d H:i:s') . " WIB";
            
            // Send to Telegram
            $response = $telegram->sendMessage($message);
            
            // Update status
            $this->signal->update([
                'telegram_sent' => true,
                'telegram_sent_at' => now(),
                'telegram_response' => json_encode($response)
            ]);
            
            Log::info("✅ Signal #{$this->signal->id} sent to Telegram");
            
        } catch (\Exception $e) {
            Log::error("❌ Signal #{$this->signal->id} failed: {$e->getMessage()}");
            
            // Retry job if attempts remaining
            if ($this->attempts() < $this->tries) {
                $this->release($this->backoff[$this->attempts() - 1] ?? 60);
            } else {
                // Mark as failed after all retries
                $this->signal->update([
                    'telegram_response' => 'Failed after ' . $this->tries . ' attempts: ' . $e->getMessage()
                ]);
            }
            
            throw $e;
        }
    }
}
