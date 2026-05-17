<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('methods');

        if (! $schema->hasTable('qc_signal') || $schema->hasColumn('qc_signal', 'force_exit')) {
            return;
        }

        $schema->table('qc_signal', function (Blueprint $table) {
            $table->boolean('force_exit')->default(false)->after('market_type');
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('methods');

        if (! $schema->hasTable('qc_signal') || ! $schema->hasColumn('qc_signal', 'force_exit')) {
            return;
        }

        $schema->table('qc_signal', function (Blueprint $table) {
            $table->dropColumn('force_exit');
        });
    }
};
