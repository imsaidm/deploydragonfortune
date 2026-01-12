<?php

namespace App\Observers;

use App\Models\QcReminder;

class QcReminderObserver
{
    /**
     * Handle the QcReminder "created" event.
     */
    public function created(QcReminder $qcReminder): void
    {
        // Dispatch job to send Telegram notification
        \App\Jobs\SendTelegramReminderJob::dispatch($qcReminder);
    }

    /**
     * Handle the QcReminder "updated" event.
     */
    public function updated(QcReminder $qcReminder): void
    {
        //
    }

    /**
     * Handle the QcReminder "deleted" event.
     */
    public function deleted(QcReminder $qcReminder): void
    {
        //
    }

    /**
     * Handle the QcReminder "restored" event.
     */
    public function restored(QcReminder $qcReminder): void
    {
        //
    }

    /**
     * Handle the QcReminder "force deleted" event.
     */
    public function forceDeleted(QcReminder $qcReminder): void
    {
        //
    }
}
