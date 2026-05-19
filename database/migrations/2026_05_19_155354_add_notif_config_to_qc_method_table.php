<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $schema = Schema::connection('methods');
        if (! $schema->hasTable('qc_method') || $schema->hasColumn('qc_method', 'config_notif')) {
            return;
        }

        $schema->table('qc_method', function (Blueprint $table) {
            $table->integer('config_notif')->default(0)->after('onactive');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $schema = Schema::connection('methods');

        if (! $schema->hasTable('qc_method') || ! $schema->hasColumn('qc_method', 'config_notif')) {
            return;
        }

        $schema->table('qc_method', function (Blueprint $table) {
            $table->dropColumn('config_notif');
        });
    }
};
