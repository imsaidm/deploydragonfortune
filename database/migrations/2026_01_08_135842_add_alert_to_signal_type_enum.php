<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add 'alert' to the signal_type enum for pre-signal notifications
     */
    public function up(): void
    {
        // Modify ENUM to include 'alert' type
        DB::statement("ALTER TABLE quantconnect_signals MODIFY COLUMN signal_type ENUM('entry', 'exit', 'alert', 'update', 'error') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove 'alert' from ENUM (revert to original)
        DB::statement("ALTER TABLE quantconnect_signals MODIFY COLUMN signal_type ENUM('entry', 'exit', 'update', 'error') NOT NULL");
    }
};
