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
    public $backoff = [10, 30, 60];

    public function __construct(
        public QcSignal $signal,
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
        if ($this->signal->telegram_sent) {
            return;
        }

        $this->signal->load('method.telegramChannels');
        $method = $this->signal->method;

        $chatIds = [];
        if ($method && $method->telegramChannels->count() > 0) {
            $chatIds = $method->telegramChannels->where('is_active', true)->pluck('chat_id')->toArray();
        }

        // Fallback to default channels if no method-specific channels
        if (empty($chatIds)) {
            $isProduction = $method ? (bool) $method->is_production : false;
            // Use sendMessage logic to get default chat IDs
            $chatIds = $isProduction ? [config('services.telegram.chat_id')] : [config('services.telegram.dev_chat_id') ?: config('services.telegram.chat_id')];
        }

        $chatIds = array_filter(array_unique($chatIds));

        // 1. Mark as processing to prevent other fan-out jobs
        $this->signal->update(['telegram_processing' => true]);

        // ... existing channel identification code ...

        $chatIds = array_filter(array_unique($chatIds));

        // 2. Mark as sent globally BEFORE dispatching to prevent scheduler race condition
        $this->signal->update([
            'telegram_sent' => true,
            'telegram_sent_at' => now(),
            'telegram_processing' => false
        ]);

        foreach ($chatIds as $index => $cid) {
            // [SLANKER]: Delay bertahap (+ jitter) supaya tidak barengan hit API
            $delaySeconds = ($index * 3) + rand(0, 2); 
            SendTelegramSignalJob::dispatch($this->signal, $cid)->delay(now()->addSeconds($delaySeconds));
        }

        \Illuminate\Support\Facades\Cache::forget('dispatch_tele_signal_' . $this->signal->id);
    }

    /**
     * Process sending to a specific chat ID.
     */
    private function processSingleTarget(TelegramNotificationService $telegram): void
    {
        $lockKey = 'active_tele_job_' . $this->signal->id . '_' . $this->chatId;
        
        // Prevent concurrent execution of the SAME job on the SAME target
        if (!\Illuminate\Support\Facades\Cache::add($lockKey, true, 300)) {
            return;
        }

        try {
            $this->signal->load('method');
            $method = $this->signal->method;

            $isEntry = strtolower($this->signal->type) === 'entry';
            $jenis = strtolower($this->signal->jenis);
            $isBuy = in_array($jenis, ['buy', 'long']);

            $directionEmoji = $isBuy ? '🟢' : '🔴';
            $directionText = strtoupper($this->signal->jenis);

            if ($isEntry) {
                $message = $this->buildEntryMessage($method, $directionEmoji, $directionText);
            } else {
                $message = $this->buildExitMessage($method, $directionEmoji, $directionText, $isBuy);
            }

            $uniqueLockKey = "unique_sig_{$this->signal->id}";
            $token = null;

            // Determine token (Production vs Dev) if using defaults
            if ($method && $this->chatId === config('services.telegram.dev_chat_id')) {
                $token = config('services.telegram.dev_bot_token');
            }

            $response = $telegram->sendToSingleChannel($this->chatId, $message, $token, $uniqueLockKey);

            if (!$response['success'] && !isset($response['skipped'])) {
                throw new \Exception("Telegram send failed for {$this->chatId}: " . ($response['error'] ?? 'Unknown error'));
            }

            // Optional: Update internal status log in DB if needed
            // (Since telegram_sent is now global, we might want to log individual successes in telegram_response)

            \Illuminate\Support\Facades\Cache::forget($lockKey);

        } catch (\Exception $e) {
            Log::error("❌ Signal #{$this->signal->id} for Group {$this->chatId} failed: {$e->getMessage()}");
            
            \Illuminate\Support\Facades\Cache::forget($lockKey);

            // Re-throw to trigger Laravel's try/backoff
            throw $e;
        }
    }

    private function buildEntryMessage($method, string $directionEmoji, string $directionText): string
    {
        $entryPrice = (float) $this->signal->price_entry;
        $tpPrice = (float) $this->signal->target_tp;
        $slPrice = (float) $this->signal->target_sl;

        $potentialProfit = abs($tpPrice - $entryPrice);
        $potentialLoss = abs($entryPrice - $slPrice);
        $rrRatio = $potentialLoss > 0 ? round($potentialProfit / $potentialLoss, 2) : 0;

        $tpPercent = $entryPrice > 0 ? round(($potentialProfit / $entryPrice) * 100, 2) : 0;
        $slPercent = $entryPrice > 0 ? round(($potentialLoss / $entryPrice) * 100, 2) : 0;

        $message = "━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "🐉 *DRAGONFORTUNE AI SIGNAL*\n";
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

            $message .= "📈 *Performance KPI*\n";
            $message .= "├ CAGR: `" . number_format($method->cagr, 2) . "%`\n";
            $message .= "├ Max DD: `" . number_format($method->drawdown, 2) . "%`\n";
            $message .= "├ Winrate: `" . number_format($method->winrate, 1) . "%`\n";
            $message .= "├ Sharpe: `" . number_format($method->sharpen_ratio, 3) . "`\n";
            $message .= "├ Sortino: `" . number_format($method->sortino_ratio, 3) . "`\n";
            $message .= "└ Total Trades: `" . number_format($method->total_orders, 0) . "`\n\n";
        }

        $message .= "━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "📥 {$directionEmoji} *ENTRY {$directionText}* {$directionEmoji}\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━\n\n";

        $message .= "💰 *Entry Price*\n";
        $message .= "└ `\$ " . number_format($entryPrice, 2) . "`\n\n";

        $message .= "🎯 *Target Take Profit*\n";
        $message .= "├ Price: `\$ " . number_format($tpPrice, 2) . "`\n";
        $message .= "└ Gain: `+{$tpPercent}%`\n\n";

        $message .= "🛡️ *Target Stop Loss*\n";
        $message .= "├ Price: `\$ " . number_format($slPrice, 2) . "`\n";
        $message .= "└ Risk: `-{$slPercent}%`\n\n";

        $message .= "⚖️ *Risk/Reward Ratio*: `1:{$rrRatio}`\n\n";

        $message .= "━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "⏰ " . now()->setTimezone('Asia/Jakarta')->format('d M Y, H:i:s') . " WIB\n";
        $message .= "🤖 _Powered by DragonFortune AI_\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━";

        return $message;
    }

    private function buildExitMessage($method, string $directionEmoji, string $directionText, bool $isBuy): string
    {
        $entryPrice = (float) $this->signal->price_entry;
        $exitPrice = (float) $this->signal->price_exit;
        $realTp = (float) $this->signal->real_tp;
        $realSl = (float) $this->signal->real_sl;

        // Calculate P/L
        $jenis = strtolower($this->signal->jenis);

        // Determine Logic based on 'jenis' label AND historical data patterns:
        // - 'short': Explicitly a Short trade -> P/L = Entry - Exit
        // - 'buy': On an Exit signal, this usually means 'Cover Short' -> P/L = Entry - Exit
        // - 'sell': On an Exit signal, this usually means 'Close Long' -> P/L = Exit - Entry
        // - 'long': Explicitly a Long trade -> P/L = Exit - Entry

        $useShortLogic = ($jenis === 'short' || $jenis === 'buy');

        $priceDiff = $useShortLogic ? ($entryPrice - $exitPrice) : ($exitPrice - $entryPrice);
        $plPercent = $entryPrice > 0 ? round(($priceDiff / $entryPrice) * 100, 2) : 0;
        $isProfit = $priceDiff >= 0;

        // Determine result
        $resultEmoji = $isProfit ? '✅' : '❌';
        $resultText = $isProfit ? 'PROFIT' : 'LOSS';
        $plSign = $isProfit ? '+' : '';

        $message = "━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "🐉 *DRAGONFORTUNE AI SIGNAL*\n";
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
        }

        $message .= "━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "📤 {$directionEmoji} *EXIT {$directionText}* {$directionEmoji}\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━\n\n";

        $message .= "📊 *Trade Summary*\n";
        $message .= "├ Entry: `\$ " . number_format($entryPrice, 2) . "`\n";
        $message .= "├ Exit: `\$ " . number_format($exitPrice, 2) . "`\n";
        $message .= "└ Direction: `{$directionText}`\n\n";

        // Show result prominently
        $message .= "━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "{$resultEmoji} *RESULT: {$resultText}* {$resultEmoji}\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━\n\n";

        $message .= "💵 *P/L Details*\n";
        $message .= "├ Amount: `{$plSign}\$ " . number_format(abs($priceDiff), 2) . "`\n";
        $message .= "└ Percentage: `{$plSign}{$plPercent}%`\n\n";

        // Show realized TP/SL
        if ($realTp > 0) {
            $message .= "🎯 *Realisasi TP*: `\$ " . number_format($realTp, 2) . "`\n";
        }
        if ($realSl > 0) {
            $message .= "🛑 *Realisasi SL*: `\$ " . number_format($realSl, 2) . "`\n";
        }

        // if ($method) {
        //     $message .= "\n📈 *Updated KPI*\n";
        //     $message .= "├ Winrate: `" . number_format($method->winrate, 1) . "%`\n";
        //     $message .= "└ Total Trades: `" . number_format($method->total_orders, 0) . "`\n";
        // }

        $message .= "\n━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "⏰ " . now()->setTimezone('Asia/Jakarta')->format('d M Y, H:i:s') . " WIB\n";
        $message .= "🤖 _Powered by DragonFortune AI_\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━";

        return $message;
    }
}
