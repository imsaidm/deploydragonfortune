<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuantconnectProjectSession extends Model
{
    protected $fillable = [
        'project_id',
        'project_name',
        'is_live',
        'status',
        'last_signal_at',
        'last_heartbeat_at'
    ];

    protected $casts = [
        'is_live' => 'boolean',
        'last_signal_at' => 'datetime',
        'last_heartbeat_at' => 'datetime'
    ];

    /**
     * Get all signals for this project session
     */
    public function signals(): HasMany
    {
        return $this->hasMany(QuantconnectSignal::class, 'project_id', 'project_id');
    }

    /**
     * Get the latest signals for this project
     */
    public function latestSignals(): HasMany
    {
        return $this->signals()->latest();
    }

    /**
     * Get status badge HTML class
     */
    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'active' => 'bg-green-100 text-green-800',
            'stopped' => 'bg-gray-100 text-gray-800',
            'error' => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800'
        };
    }

    /**
     * Get project type badge (Live/Backtest)
     */
    public function getTypeBadgeAttribute(): string
    {
        return $this->is_live
            ? 'bg-blue-100 text-blue-800'
            : 'bg-yellow-100 text-yellow-800';
    }

    /**
     * Get project type label
     */
    public function getTypeAttribute(): string
    {
        return $this->is_live ? 'Live' : 'Backtest';
    }

    /**
     * Update the last signal timestamp
     */
    public function updateLastSignal(): void
    {
        $this->update([
            'last_signal_at' => now()
        ]);
    }

    /**
     * Scope to get active projects
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to get live projects
     */
    public function scopeLive($query)
    {
        return $query->where('is_live', true);
    }

    /**
     * Scope to get backtest projects
     */
    public function scopeBacktest($query)
    {
        return $query->where('is_live', false);
    }

    /**
     * Get signals count for this project
     */
    public function getSignalsCountAttribute(): int
    {
        return $this->signals()->count();
    }

    /**
     * Get recent signals count (last 24 hours)
     */
    public function getRecentSignalsCountAttribute(): int
    {
        return $this->signals()
            ->where('signal_timestamp', '>=', now()->subDay())
            ->count();
    }

    /**
     * Get the last signal received for this project
     */
    public function getLastSignalAttribute(): ?QuantconnectSignal
    {
        return $this->signals()
            ->latest('signal_timestamp')
            ->first();
    }

    /**
     * Check if project is currently active (received signals recently)
     */
    public function getIsActiveAttribute(): bool
    {
        return $this->last_signal_at && $this->last_signal_at->gt(now()->subHours(2));
    }

    /**
     * Get project activity status
     */
    public function getActivityStatusAttribute(): string
    {
        if (!$this->last_signal_at) {
            return 'inactive';
        }

        $hoursSinceLastSignal = now()->diffInHours($this->last_signal_at);

        if ($hoursSinceLastSignal <= 1) {
            return 'active';
        } elseif ($hoursSinceLastSignal <= 6) {
            return 'idle';
        } else {
            return 'inactive';
        }
    }

    /**
     * Update project status based on recent activity
     */
    public function updateActivityStatus(): void
    {
        $activityStatus = $this->activity_status;

        // Update status based on activity
        if ($activityStatus === 'active' && $this->status !== 'active') {
            $this->update(['status' => 'active']);
        } elseif ($activityStatus === 'inactive' && $this->status === 'active') {
            $this->update(['status' => 'stopped']);
        }
    }
}
