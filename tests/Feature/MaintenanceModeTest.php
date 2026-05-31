<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MaintenanceModeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['maintenance.fail_closed' => false]);

        Schema::dropIfExists('system_settings');
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Cache::store('file')->forget(SystemSetting::MAINTENANCE_CACHE_KEY);
        Cache::store('file')->forget(SystemSetting::MAINTENANCE_LAST_KNOWN_CACHE_KEY);
    }

    protected function tearDown(): void
    {
        Cache::store('file')->forget(SystemSetting::MAINTENANCE_CACHE_KEY);
        Cache::store('file')->forget(SystemSetting::MAINTENANCE_LAST_KNOWN_CACHE_KEY);
        Schema::dropIfExists('system_settings');

        parent::tearDown();
    }

    public function test_web_requests_show_maintenance_page_when_enabled(): void
    {
        SystemSetting::setMaintenanceMode(true);

        $response = $this->get('/');

        $response->assertStatus(503);
        $response->assertSee('The site is currently down for maintenance');
    }

    public function test_api_requests_return_json_when_enabled(): void
    {
        SystemSetting::setMaintenanceMode(true);

        $response = $this->getJson('/api/status');

        $response->assertStatus(503);
        $response->assertJson([
            'status' => 'maintenance',
        ]);
    }

    public function test_last_known_enabled_flag_blocks_when_settings_table_is_missing(): void
    {
        SystemSetting::setMaintenanceMode(true);
        Schema::dropIfExists('system_settings');
        Cache::store('file')->forget(SystemSetting::MAINTENANCE_CACHE_KEY);

        $response = $this->get('/');

        $response->assertStatus(503);
        $response->assertSee('The site is currently down for maintenance');
    }

    public function test_maintenance_page_does_not_require_database_sessions(): void
    {
        SystemSetting::setMaintenanceMode(true);
        Schema::dropIfExists('system_settings');
        Cache::store('file')->forget(SystemSetting::MAINTENANCE_CACHE_KEY);
        config(['session.driver' => 'database']);
        app('session')->forgetDrivers();

        $response = $this->get('/');

        $response->assertStatus(503);
        $response->assertSee('The site is currently down for maintenance');
    }

    public function test_web_requests_continue_when_disabled(): void
    {
        SystemSetting::setMaintenanceMode(false);

        $this->get('/')->assertStatus(200);
    }
}
