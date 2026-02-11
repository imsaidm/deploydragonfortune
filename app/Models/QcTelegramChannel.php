<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class QcTelegramChannel extends Model
{
    protected $connection = 'methods';
    protected $table = 'qc_telegram_channels';

    protected $fillable = [
        'name',
        'chat_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * The methods that belong to the channel.
     */
    public function methods(): BelongsToMany
    {
        return $this->belongsToMany(
            QcMethod::class,
            'qc_method_telegram_channel',
            'id_channel',
            'id_method'
        );
    }
}
