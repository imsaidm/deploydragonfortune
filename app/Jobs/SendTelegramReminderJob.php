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
            // Load method relationship
            $this->reminder->load('method');
            $method = $this->reminder->method;
            
            // Build professional reminder message
            $message = "━━━━━━━━━━━━━━━━━━━━━━\n";
            $message .= "🔔 *DRAGONFORTUNE REMINDER*\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━━━\n\n";
            
            // Method Information (if available)
            if ($method) {
                $message .= "📊 *Strategy Info*\n";
                $message .= "├ Name: `{$method->nama_metode}`\n";
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
            $message .= "{$this->reminder->message}\n\n";
            
            // Footer
            $message .= "━━━━━━━━━━━━━━━━━━━━━━\n";
            $message .= "⏰ " . now()->setTimezone('Asia/Jakarta')->format('d M Y, H:i:s') . " WIB\n";
            $message .= "🤖 _Powered by DragonFortune AI_\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━━━";
            
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
