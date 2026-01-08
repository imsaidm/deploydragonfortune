<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuantconnectSignal extends Model
{
    protected $fillable = [
        'project_id',
        'project_name',
        'signal_type',
        'symbol',
        'action',
        'price',
        'quantity',
        'target_price',
        'stop_loss',
        'realized_pnl',
        'message',
        'raw_payload',
        'webhook_received_at',
        'signal_timestamp'
    ];

    protected $casts = [
        'raw_payload' => 'array',
        'webhook_received_at' => 'datetime',
        'signal_timestamp' => 'datetime',
        'price' => 'decimal:8',
        'quantity' => 'decimal:8',
        'target_price' => 'decimal:8',
        'stop_loss' => 'decimal:8',
        'realized_pnl' => 'decimal:8',
        'is_live' => 'boolean'
    ];

    /**
     * Get the project session that owns this signal
     */
    public function projectSession(): BelongsTo
    {
        return $this->belongsTo(QuantconnectProjectSession::class, 'project_id', 'project_id');
    }

    /**
     * Get formatted price with currency symbol
     */
    public function getFormattedPriceAttribute(): string
    {
        return '$' . number_format($this->price, 2);
    }

    /**
     * Get color class for PnL display
     */
    public function getPnlColorAttribute(): string
    {
        if ($this->realized_pnl === null) {
            return 'text-gray-500';
        }
        
        return $this->realized_pnl >= 0 ? 'text-green-500' : 'text-red-500';
    }

    /**
     * Scope to filter signals by project
     */
    public function scopeByProject($query, int $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    /**
     * Scope to filter signals by symbol
     */
    public function scopeBySymbol($query, string $symbol)
    {
        return $query->where('symbol', $symbol);
    }

    /**
     * Scope to filter signals by date range
     */
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('signal_timestamp', [$startDate, $endDate]);
    }

    /**
     * Scope to filter signals by type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('signal_type', $type);
    }

    /**
     * Scope to get recent signals
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('signal_timestamp', '>=', now()->subDays($days));
    }

    /**
     * Scope to order by signal timestamp
     */
    public function scopeLatest($query)
    {
        return $query->orderBy('signal_timestamp', 'desc');
    }
}
