<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('quantconnect_project_sessions', function (Blueprint $table) {
            $table->timestamp('last_heartbeat_at')->nullable()->after('last_signal_at');
        });

        // Update status enum to include 'running'
        // Note: For SQLite (testing) we can't modify enum, for MySQL we need raw query
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE quantconnect_project_sessions MODIFY COLUMN status ENUM('active', 'stopped', 'error', 'running') DEFAULT 'active'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quantconnect_project_sessions', function (Blueprint $table) {
            $table->dropColumn('last_heartbeat_at');
        });
    }
};
