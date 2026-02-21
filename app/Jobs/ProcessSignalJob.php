<?php

namespace App\Jobs;

use App\Models\TradingAccount;
use App\Models\SignalMirrorStatus;
use App\Models\QcSignal;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ProcessSignalJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $signalId;

    public function __construct(int $signalId)
    {
        $this->signalId = $signalId;
    }

    public function handle()
    {
        // GEMBOK DATABASE: Mencegah worker kloningan bikin error 1ms
        $mirrorStatus = DB::transaction(function () {
            // Cek apakah sinyal ini sudah dibuat statusnya? Kalau sudah, BATALKAN.
            if (SignalMirrorStatus::where('qc_signal_id', $this->signalId)->exists()) {
                return null; 
            }

            // Kunci baris sinyal ini
            $signal = QcSignal::where('id', $this->signalId)->lockForUpdate()->first();
            if (!$signal) return null;

            // Buat record baru di DALAM gembok transaksi
            return SignalMirrorStatus::create([
                'qc_signal_id' => $signal->id,
                'strategy_id' => $signal->id_method,
                'status' => 'processing', 
            ]);
        });

        // Kalau null, berarti worker lain udah ngerjain ini duluan. Stop sekarang juga.
        if (!$mirrorStatus) {
            Log::info("🛡️ ProcessSignalJob: Signal ID {$this->signalId} sudah diproses worker lain. Dibatalkan untuk cegah error.");
            return;
        }

        // --- LANJUT KE PROSES NORMAL ---
        $signal = QcSignal::find($this->signalId);
        $strategyId = $signal->id_method;

        try {
            $method = DB::connection('methods')->table('qc_method')->where('id', $strategyId)->first();
            $targetExchange = strtolower($method->exchange ?? 'binance');

            $accountIds = DB::connection('mysql')->table('strategy_accounts')
                ->where('strategy_id', $strategyId)
                ->where('is_active', true)
                ->pluck('account_id');

            $accounts = TradingAccount::whereIn('id', $accountIds)->where('is_active', true)->get();

            Log::info("ProcessSignalJob: Strategy [ID: {$strategyId}] target exchange: [{$targetExchange}]. Found " . $accounts->count() . " linked accounts.");

            $matchedCount = 0;
            foreach ($accounts as $account) {
                $accountExchange = strtolower($account->exchange ?: 'binance');
                
                if ($accountExchange === $targetExchange) {
                    AccountExecutionJob::dispatch($account, $signal->id, $strategyId);
                    $matchedCount++;
                } else {
                    Log::warning("ProcessSignalJob: Skipping account [ID: {$account->id}] ({$accountExchange}) because it does not match strategy exchange ({$targetExchange}).");
                }
            }

            Log::info("ProcessSignalJob: Dispatched AccountExecutionJob for {$matchedCount} accounts matching [{$targetExchange}].");

            $mirrorStatus->update([
                'status' => 'completed',
                'processed_at' => now()
            ]);

        } catch (\Exception $e) {
            Log::error("ProcessSignalJob failed for Signal {$this->signalId}: " . $e->getMessage());
            $mirrorStatus->update(['status' => 'failed']);
            throw $e;
        }
    }
}