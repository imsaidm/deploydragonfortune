<?php

namespace App\Console\Commands;

use App\Jobs\ProcessSignalJob;
use App\Models\QcSignal;
use App\Models\SignalMirrorStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessPendingOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:process-pending {--limit=10 : Maximum records to process per run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process pending signals that were created via PDO and haven\'t been processed yet';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        // Get IDs of signals that have already been processed or are being processed
        // SignalMirrorStatus is on the default 'mysql' connection
        $processedSignalIds = SignalMirrorStatus::pluck('qc_signal_id')->toArray();

        // Query pending signals from the 'methods' connection
        // We only look for recent signals (e.g., last 1 hour) to avoid processing ancient data if any
        $pendingSignals = QcSignal::whereNotIn('id', $processedSignalIds)
            ->where('created_at', '>', now()->subHours(1))
            ->orderBy('id', 'asc')
            ->limit($limit)
            ->get();

        if ($pendingSignals->isEmpty()) {
            return Command::SUCCESS;
        }

        $this->info("Found " . $pendingSignals->count() . " pending signals.");

        foreach ($pendingSignals as $signal) {
            try {
                // Dispatch job to process signal for multi-account copy-trading
                ProcessSignalJob::dispatch($signal->id);
                
                $this->info("Dispatched ProcessSignalJob for signal ID: {$signal->id}");
                Log::info('PDO Signal detected and processing dispatched', ['id' => $signal->id]);
            } catch (\Exception $e) {
                $this->error("Failed to dispatch job for signal ID: {$signal->id} - {$e->getMessage()}");
                Log::error('Failed to dispatch processing for PDO signal', [
                    'id' => $signal->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return Command::SUCCESS;
    }
}
