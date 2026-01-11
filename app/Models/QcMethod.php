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
        'api_key',
        'secret_key',
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
    
    protected $hidden = [
        'api_key',
        'secret_key',
    ];
    
    // Accessors & Mutators for API Key Encryption
    public function setApiKeyAttribute($value)
    {
        if ($value) {
            $this->attributes['api_key'] = encrypt($value);
        }
    }
    
    public function getApiKeyAttribute($value)
    {
        if ($value) {
            try {
                return decrypt($value);
            } catch (\Exception $e) {
                return $value;
            }
        }
        return null;
    }
    
    public function setSecretKeyAttribute($value)
    {
        if ($value) {
            $this->attributes['secret_key'] = encrypt($value);
        }
    }
    
    public function getSecretKeyAttribute($value)
    {
        if ($value) {
            try {
                return decrypt($value);
            } catch (\Exception $e) {
                return $value;
            }
        }
        return null;
    }
    
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
}
