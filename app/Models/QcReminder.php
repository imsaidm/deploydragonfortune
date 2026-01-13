<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Observers\QcReminderObserver;

#[ObservedBy([QcReminderObserver::class])]
class QcReminder extends Model
{
    /**
     * Get the method that this reminder belongs to.
     */
    public function method(): BelongsTo
    {
        return $this->belongsTo(QcMethod::class, 'id_method');
    }

    // Use 'methods' connection (simulates remote database)
    protected $connection = 'methods';
    protected $table = 'qc_reminders';
    
    protected $fillable = [
        'id_method',
        'datetime',
        'message',
        'telegram_sent',
        'telegram_sent_at',
        'telegram_response',
    ];
    
    protected $casts = [
        'datetime' => 'datetime',
        'telegram_sent' => 'boolean',
        'telegram_sent_at' => 'datetime',
    ];
}
