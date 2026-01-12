<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use App\Observers\QcReminderObserver;

#[ObservedBy([QcReminderObserver::class])]
class QcReminder extends Model
{
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
