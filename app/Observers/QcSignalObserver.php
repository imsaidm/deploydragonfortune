<?php

namespace App\Observers;

use App\Models\QcSignal;

class QcSignalObserver
{
    /**
     * Handle the QcSignal "created" event.
     */
    public function created(QcSignal $qcSignal): void
    {
        // Dispatch job to process signal for multi-account copy-trading
        \App\Jobs\ProcessSignalJob::dispatch($qcSignal->id);
        
        // Dispatch job to send Telegram notification (if enabled)
        \App\Jobs\SendTelegramSignalJob::dispatch($qcSignal);
    }

    /**
     * Handle the QcSignal "updated" event.
     */
    public function updated(QcSignal $qcSignal): void
    {
        //
    }

    /**
     * Handle the QcSignal "deleted" event.
     */
    public function deleted(QcSignal $qcSignal): void
    {
        //
    }

    /**
     * Handle the QcSignal "restored" event.
     */
    public function restored(QcSignal $qcSignal): void
    {
        //
    }

    /**
     * Handle the QcSignal "force deleted" event.
     */
    public function forceDeleted(QcSignal $qcSignal): void
    {
        //
    }
}
