<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TradingAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_name',
        'api_key',
        'secret_key',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function strategies()
    {
        return $this->belongsToMany(QcMethod::class, 'newera.strategy_accounts', 'account_id', 'strategy_id')
            ->withPivot('is_active')
            ->withTimestamps();
    }

    public function executions()
    {
        return $this->hasMany(Execution::class, 'account_id');
    }

    public function positions()
    {
        return $this->hasMany(Position::class, 'account_id');
    }
}
