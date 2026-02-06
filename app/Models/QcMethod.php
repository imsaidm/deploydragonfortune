<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QcMethod extends Model
{
    // Use 'methods' connection (simulates remote database)
    protected $connection = 'methods';
    protected $table = 'qc_method';
    
    protected $fillable = [
        'nama_metode',
        'pair',
        'tf',
        'exchange',
        'cagr',
        'drawdown',
        'winrate',
        'lossrate',
        'prob_sr',
        'sharpen_ratio',
        'sortino_ratio',
        'information_ratio',
        'turnover',
        'total_orders',
        'kpi_extra',
        'url',
        'api_key',
        'secret_key',
        'onactive',
    ];
    
    protected $casts = [
        'cagr' => 'decimal:6',
        'drawdown' => 'decimal:6',
        'winrate' => 'decimal:6',
        'lossrate' => 'decimal:6',
        'prob_sr' => 'decimal:6',
        'sharpen_ratio' => 'decimal:6',
        'sortino_ratio' => 'decimal:6',
        'information_ratio' => 'decimal:6',
        'turnover' => 'decimal:6',
        'total_orders' => 'decimal:6',
        'kpi_extra' => 'array',
        'onactive' => 'boolean',
    ];

    /**
     * Get the signals for this method.
     */
    public function signals(): HasMany
    {
        return $this->hasMany(QcSignal::class, 'id_method');
    }

    /**
     * Get the trading accounts for this strategy.
     */
    public function tradingAccounts()
    {
        return $this->belongsToMany(TradingAccount::class, 'newera.strategy_accounts', 'strategy_id', 'account_id')
            ->withPivot('is_active')
            ->withTimestamps();
    }
}
