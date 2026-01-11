<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QcMethod extends Model
{
    protected $table = 'qc_methods';
    
    protected $fillable = [
        'nama_metode',
        'market_type',
        'pair',
        'tf',
        'exchange',
        'master_exchange_id',
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
        'qc_url',
        'qc_project_id',
        'webhook_token',
        'risk_settings',
        'is_active',
        'auto_trade_enabled',
        'last_signal_at',
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
        'risk_settings' => 'array',
        'is_active' => 'boolean',
        'auto_trade_enabled' => 'boolean',
        'last_signal_at' => 'datetime',
    ];
    
    
    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
    
    public function scopeAutoTradeEnabled($query)
    {
        return $query->where('auto_trade_enabled', true);
    }
    
    public function scopeSpot($query)
    {
        return $query->where('market_type', 'SPOT');
    }
    
    public function scopeFutures($query)
    {
        return $query->where('market_type', 'FUTURES');
    }
    
    // Relationships
    public function signals()
    {
        return $this->hasMany(QuantConnectSignal::class, 'qc_id', 'qc_project_id');
    }
    
    public function masterExchange()
    {
        return $this->belongsTo(MasterExchange::class, 'master_exchange_id');
    }
    
    // Helper Methods
    public function isSpot(): bool
    {
        return $this->market_type === 'SPOT';
    }
    
    public function isFutures(): bool
    {
        return $this->market_type === 'FUTURES';
    }
    
    public function generateWebhookToken(): string
    {
        return bin2hex(random_bytes(32));
    }
    
    /**
     * Get API credentials from master exchange
     */
    public function getApiCredentials(): ?array
    {
        // Use master exchange credentials only
        if ($this->masterExchange && $this->masterExchange->is_active) {
            return [
                'api_key' => $this->masterExchange->api_key,
                'secret_key' => $this->masterExchange->secret_key,
                'testnet' => $this->masterExchange->testnet,
                'exchange_type' => $this->masterExchange->exchange_type,
                'market_type' => $this->masterExchange->market_type,
            ];
        }
        
        return null;
    }
}
