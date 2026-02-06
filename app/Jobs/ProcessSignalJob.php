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
            $accountIds = DB::connection('mysql')->table('strategy_accounts')
                ->where('strategy_id', $strategyId)
                ->where('is_active', true)
                ->pluck('account_id');

            $accounts = TradingAccount::whereIn('id', $accountIds)
                ->where('is_active', true)
                ->get();

            Log::info("ProcessSignalJob: Found " . $accounts->count() . " accounts for Master Method ID " . $strategyId);

            foreach ($accounts as $account) {
                AccountExecutionJob::dispatch($account, $signal->id, $strategyId);
            }

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
