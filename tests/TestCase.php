<?php

namespace Tests;

use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config(['maintenance.fail_closed' => false]);
        Cache::store('file')->forget(SystemSetting::MAINTENANCE_CACHE_KEY);
        Cache::store('file')->forget(SystemSetting::MAINTENANCE_LAST_KNOWN_CACHE_KEY);
    }
}
