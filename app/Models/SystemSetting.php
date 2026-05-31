<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SystemSetting extends Model
{
    public const MAINTENANCE_MODE = 'maintenance_mode';
    public const MAINTENANCE_CACHE_KEY = 'dragonfortune.maintenance_mode';
    public const MAINTENANCE_LAST_KNOWN_CACHE_KEY = 'dragonfortune.maintenance_mode.last_known';

    protected $fillable = [
        'name',
        'value',
    ];

    public static function maintenanceModeEnabled(): bool
    {
        if (filter_var(config('maintenance.enabled', false), FILTER_VALIDATE_BOOL)) {
            return true;
        }

        return (bool) Cache::store('file')->remember(
            self::MAINTENANCE_CACHE_KEY,
            self::maintenanceCacheTtl(),
            fn () => self::readMaintenanceModeFlag(),
        );
    }

    public static function uncachedMaintenanceModeEnabled(): bool
    {
        if (filter_var(config('maintenance.enabled', false), FILTER_VALIDATE_BOOL)) {
            return true;
        }

        return self::readMaintenanceModeFlag();
    }

    public static function setMaintenanceMode(bool $enabled): void
    {
        self::query()->updateOrCreate(
            ['name' => self::MAINTENANCE_MODE],
            ['value' => $enabled ? '1' : '0'],
        );

        Cache::store('file')->put(
            self::MAINTENANCE_CACHE_KEY,
            $enabled,
            self::maintenanceCacheTtl(),
        );

        Cache::store('file')->forever(self::MAINTENANCE_LAST_KNOWN_CACHE_KEY, $enabled);
    }

    public static function maintenanceModeValueFromDatabase(): ?bool
    {
        try {
            if (! Schema::hasTable((new self())->getTable())) {
                return null;
            }

            $value = self::query()
                ->where('name', self::MAINTENANCE_MODE)
                ->value('value');

            return $value === null ? false : filter_var($value, FILTER_VALIDATE_BOOL);
        } catch (Throwable) {
            return null;
        }
    }

    private static function readMaintenanceModeFlag(): bool
    {
        try {
            if (! Schema::hasTable((new self())->getTable())) {
                return self::lastKnownMaintenanceMode() ?? false;
            }

            $enabled = self::query()
                ->where('name', self::MAINTENANCE_MODE)
                ->value('value');

            $enabled = $enabled === null ? false : filter_var($enabled, FILTER_VALIDATE_BOOL);

            Cache::store('file')->forever(self::MAINTENANCE_LAST_KNOWN_CACHE_KEY, $enabled);

            return $enabled;
        } catch (Throwable) {
            return self::lastKnownMaintenanceMode()
                ?? filter_var(config('maintenance.fail_closed', true), FILTER_VALIDATE_BOOL);
        }
    }

    private static function lastKnownMaintenanceMode(): ?bool
    {
        $value = Cache::store('file')->get(self::MAINTENANCE_LAST_KNOWN_CACHE_KEY);

        return $value === null ? null : (bool) $value;
    }

    private static function maintenanceCacheTtl(): int
    {
        return max(1, (int) config('maintenance.cache_ttl', 15));
    }
}
