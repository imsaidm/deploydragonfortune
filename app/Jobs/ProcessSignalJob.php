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

    /**
     * Create a new job instance.
     */
    public function __construct(int $signalId)
    {
        $this->signalId = $signalId;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $signal = QcSignal::find($this->signalId);
        if (!$signal) {
            Log::error("ProcessSignalJob: Signal ID {$this->signalId} not found.");
            return;
        }

        $strategyId = $signal->id_method;
        
        // signal_mirror_status.status ENUM: pending, processing, completed, partial_failed, failed
        $mirrorStatus = SignalMirrorStatus::create([
            'qc_signal_id' => $signal->id,
            'strategy_id' => $strategyId,
            'status' => 'processing', 
        ]);

        try {
            // Get method/strategy details to know which exchange this signal is for
            $method = DB::connection('methods')->table('qc_method')->where('id', $strategyId)->first();
            $targetExchange = strtolower($method->exchange ?? 'binance');

            $accountIds = DB::connection('mysql')->table('strategy_accounts')
                ->where('strategy_id', $strategyId)
                ->where('is_active', true)
                ->pluck('account_id');

            $accounts = TradingAccount::whereIn('id', $accountIds)
                ->where('is_active', true)
                ->get();

            Log::info("ProcessSignalJob: Strategy [ID: {$strategyId}] target exchange: [{$targetExchange}]. Found " . $accounts->count() . " linked accounts.");

            $matchedCount = 0;
            foreach ($accounts as $account) {
                $accountExchange = strtolower($account->exchange ?: 'binance');
                
                // ONLY dispatch if exchanges match (e.g., BINANCE strategy -> binance account)
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
