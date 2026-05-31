<?php

namespace App\Console\Commands;

use App\Models\SystemSetting;
use Illuminate\Console\Command;

class SetMaintenanceMode extends Command
{
    protected $signature = 'dragonfortune:maintenance {state : on, off, or status}';

    protected $description = 'Toggle the Dragon Fortune database-backed maintenance mode flag.';

    public function handle(): int
    {
        $state = strtolower((string) $this->argument('state'));

        if ($state === 'status') {
            $this->showStatus();

            return self::SUCCESS;
        }

        if (! in_array($state, ['on', 'off'], true)) {
            $this->error('State must be one of: on, off, status.');

            return self::FAILURE;
        }

        $enabled = $state === 'on';

        SystemSetting::setMaintenanceMode($enabled);

        $this->info('Maintenance mode is now '.($enabled ? 'ON' : 'OFF').'.');

        return self::SUCCESS;
    }

    private function showStatus(): void
    {
        $databaseValue = SystemSetting::maintenanceModeValueFromDatabase();
        $effectiveValue = SystemSetting::uncachedMaintenanceModeEnabled();

        $this->line('Database flag: '.match ($databaseValue) {
            true => 'ON',
            false => 'OFF',
            null => 'UNAVAILABLE',
        });

        $this->line('Effective status: '.($effectiveValue ? 'ON' : 'OFF'));
    }
}
