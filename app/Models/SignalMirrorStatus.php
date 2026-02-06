<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SignalMirrorStatus extends Model
{
    protected $table = 'signal_mirror_status';

    protected $fillable = [
        'qc_signal_id',
        'strategy_id',
        'status',
        'processed_at'
    ];
}
