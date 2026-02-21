<?php

namespace App\Console\Commands;

use App\Jobs\ProcessSignalJob;
use App\Models\QcSignal;
use App\Models\SignalMirrorStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache; // <-- Wajib dipanggil

class ProcessPendingOrders extends Command
{
    protected $signature = 'orders:process-pending {--limit=10 : Maximum records to process per run}';
    protected $description = 'Process pending signals that were created via PDO and haven\'t been processed yet';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        try {
            $processedSignalIds = SignalMirrorStatus::pluck('qc_signal_id')->toArray();
        } catch (\Exception $e) {
            Log::warning('ProcessPendingOrders: signal_mirror_status table not found, skipping.', [
                'error' => $e->getMessage(),
            ]);
            return Command::SUCCESS;
        }

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
            // [GEMBOK SAKTI] Tandai di memori selama 10 menit (600 detik). 
            // Kalau udah ditandai, skip! Biar gak disuruh berkali-kali.
            if (!Cache::add('lock_pdo_signal_' . $signal->id, true, 600)) {
                continue; 
            }

            try {
                ProcessSignalJob::dispatch($signal->id);
                
                $this->info("Dispatched ProcessSignalJob for signal ID: {$signal->id}");
                Log::info('PDO Signal detected and processing dispatched', ['id' => $signal->id]);
            } catch (\Exception $e) {
                // Kalau gagal masuk antrean, buka lagi gemboknya biar bisa dicoba lagi nanti
                Cache::forget('lock_pdo_signal_' . $signal->id);
                
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