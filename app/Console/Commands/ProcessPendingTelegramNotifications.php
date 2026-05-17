<?php

namespace App\Console\Commands;

use App\Jobs\SendTelegramSignalJob;
use App\Models\QcSignal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache; // <-- Wajib dipanggil

class ProcessPendingTelegramNotifications extends Command
{
    protected $signature = 'telegram:process-pending {--limit=10 : Maximum records to process per run}';
    protected $description = 'Process pending Telegram notifications for QC signals';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        // Process pending signals
        $signals = QcSignal::where(function ($q) {
            $q->where('telegram_sent', false)->orWhereNull('telegram_sent');
        })
            ->where('telegram_processing', false)
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->get();

        foreach ($signals as $signal) {
            // [GEMBOK DISPATCH] Nama kunci beda dari Job aktif ('active_')
            $dLock = 'dispatch_tele_signal_' . $signal->id;
            if (!Cache::add($dLock, true, 300)) {
                continue;
            }

            try {
                SendTelegramSignalJob::dispatch($signal);
                $this->info("Dispatched job for signal ID: {$signal->id}");
                Log::info('Telegram job dispatched for signal', ['id' => $signal->id]);
            } catch (\Exception $e) {
                Cache::forget($dLock);

                $this->error("Failed to dispatch job for signal ID: {$signal->id} - {$e->getMessage()}");
                Log::error('Failed to dispatch telegram job for signal', [
                    'id' => $signal->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($signals->count() > 0) {
            $this->info("Processed {$signals->count()} pending signal notifications.");
        }

        return Command::SUCCESS;
    }
}
