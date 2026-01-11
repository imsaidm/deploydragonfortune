<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterExchange extends Model
{
    protected $table = 'master_exchanges';
    
    protected $fillable = [
        'name',
        'exchange_type',
        'market_type',
        'api_key',
        'secret_key',
        'testnet',
        'description',
        'is_active',
        'last_validated_at',
    ];
    
    protected $hidden = [
        'api_key',
        'secret_key',
    ];
    
    protected $casts = [
        'testnet' => 'boolean',
        'is_active' => 'boolean',
        'last_validated_at' => 'datetime',
    ];
    
    // Accessors & Mutators for API Key Encryption
    public function setApiKeyAttribute($value)
    {
        if ($value && $value !== '***ENCRYPTED***') {
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
        if ($value && $value !== '***ENCRYPTED***') {
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
    
    // Relationships
    public function tradingMethods()
    {
        return $this->hasMany(QcMethod::class, 'master_exchange_id');
    }
    
    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
    
    public function scopeBinance($query)
    {
        return $query->where('exchange_type', 'BINANCE');
    }
    
    // Helper Methods
    public function getMethodsCountAttribute()
    {
        return $this->tradingMethods()->count();
    }
    
    public function isBinance(): bool
    {
        return $this->exchange_type === 'BINANCE';
    }
    
    public function getApiBaseUrl(): string
    {
        if ($this->testnet) {
            return match($this->exchange_type) {
                'BINANCE' => $this->market_type === 'SPOT' 
                    ? 'https://testnet.binance.vision' 
                    : 'https://testnet.binancefuture.com',
                default => '',
            };
        }
        
        return match($this->exchange_type) {
            'BINANCE' => $this->market_type === 'SPOT' 
                ? 'https://api.binance.com' 
                : 'https://fapi.binance.com',
            default => '',
        };
    }
}
