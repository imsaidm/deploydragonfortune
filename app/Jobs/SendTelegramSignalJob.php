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
        public QcSignal $signal
    ) {}

    public function handle(TelegramNotificationService $telegram): void
    {
        // [ANTI-SPAM 100% MUTLAK]: Jika sudah terkirim oleh klonengan apa pun, segera batalkan eksekusi ini.
        if ($this->signal->telegram_sent) {
            Log::info("Job aborted: Signal #{$this->signal->id} was already marked as sent in DB.");
            return;
        }

        // [LOCK AKTIF]: Pakai nama kunci beda ('active_') biar gak bentrok sama Scheduler ('dispatch_')
        $lockKey = 'active_tele_signal_' . $this->signal->id;
        if (!\Illuminate\Support\Facades\Cache::add($lockKey, true, 300)) {
            Log::info("Job skipped: Signal #{$this->signal->id} is already being executed.");
            return;
        }

        try {
            // Tandai di DB sedang diproses
            $this->signal->update(['telegram_processing' => true]);

            $this->signal->load('method.telegramChannels');
            $method = $this->signal->method;

            $isEntry = strtolower($this->signal->type) === 'entry';
            $jenis = strtolower($this->signal->jenis);
            $isBuy = in_array($jenis, ['buy', 'long']);

            // Direction styling
            $directionEmoji = $isBuy ? '🟢' : '🔴';
            $directionText = strtoupper($this->signal->jenis);

            // Build message based on signal type
            if ($isEntry) {
                $message = $this->buildEntryMessage($method, $directionEmoji, $directionText);
            } else {
                $message = $this->buildExitMessage($method, $directionEmoji, $directionText, $isBuy);
            }

            $chatIds = [];
            if ($method && $method->telegramChannels->count() > 0) {
                $chatIds = $method->telegramChannels->where('is_active', true)->pluck('chat_id')->toArray();
            }

            // Fallback to is_production logic if no specific channels are linked
            if (empty($chatIds)) {
                $isProduction = $method ? (bool) $method->is_production : false;
                $response = $telegram->sendMessage($message, $isProduction);
            } else {
                $response = $telegram->sendMessage($message, $chatIds);
            }

            // [VITAL]: Jika ada sebagian pesan yang gagal kirim (timeout, dll)
            // Lemparkan exception supaya Job ini masuk antrean Retry.
            if (isset($response['success']) && !$response['success']) {
                throw new \Exception("Beberapa grup gagal menerima pesan. Cek log Telegram API error.");
            }

            $this->signal->update([
                'telegram_sent' => true,
                'telegram_sent_at' => now(),
                'telegram_response' => json_encode($response),
                'telegram_processing' => false
            ]);

            // Lepas gembok
            \Illuminate\Support\Facades\Cache::forget($lockKey);
            \Illuminate\Support\Facades\Cache::forget('dispatch_tele_signal_' . $this->signal->id);

            Log::info("✅ Signal #{$this->signal->id} sent to Telegram");
        } catch (\Exception $e) {
            Log::error("❌ Signal #{$this->signal->id} failed: {$e->getMessage()}");

            // Lepas CUMA gembok aktif biar job ini bisa di-retry oleh Worker.
            // [VITAL]: JANGAN UBAH telegram_processing JADI FALSE! JANGAN LEPAS gembok dispatch_!
            // Supaya si Scheduler tidak terus-terusan melahirkan Klonengan Job baru ke dalam antrean.
            try {
                \Illuminate\Support\Facades\Cache::forget('active_tele_signal_' . $this->signal->id);
            } catch (\Throwable $t) {
            }

            if ($this->attempts() < $this->tries) {
                $this->release($this->backoff[$this->attempts() - 1] ?? 60);
                return; // [HENTIKAN DISINI]: Laravel otomatis mengelola retry, jangan teruskan kode ke bawah!
            } else {
                // Semua jatah retry (3x) HANGUS: Baru sekarang lepaskan proteksinya secara total
                try {
                    \Illuminate\Support\Facades\Cache::forget('dispatch_tele_signal_' . $this->signal->id);
                } catch (\Throwable $t) {
                }
                $this->signal->update([
                    'telegram_processing' => false,
                    'telegram_response' => 'Failed after ' . $this->tries . ' attempts: ' . $e->getMessage()
                ]);
            }
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
            $message .= "📊 *Strategy Info*\n";
            $message .= "├ Name: `{$method->nama_metode}`\n";
            $message .= "├ Creator: `{$method->creator}`\n";
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
            $message .= "📊 *Strategy Info*\n";
            $message .= "├ Name: `{$method->nama_metode}`\n";
            $message .= "├ Creator: `{$method->creator}`\n";
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
