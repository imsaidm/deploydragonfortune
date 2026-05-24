<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $schema = Schema::connection('methods');
        if (! $schema->hasTable('qc_method')) {
            return;
        }

        if (! $schema->hasColumn('qc_method', 'notify_up_percentage')) {
            $schema->table('qc_method', function (Blueprint $table) {
                $table->decimal('notify_up_percentage', 8, 4)->default(0)->after('onactive');
            });
        }

        if (! $schema->hasColumn('qc_method', 'notify_down_percentage')) {
            $schema->table('qc_method', function (Blueprint $table) {
                $table->decimal('notify_down_percentage', 8, 4)->default(0)->after('notify_up_percentage');
            });
        }

        if ($schema->hasColumn('qc_method', 'config_notif')) {
            DB::connection('methods')
                ->table('qc_method')
                ->update(['notify_up_percentage' => DB::raw('config_notif')]);

            $schema->table('qc_method', function (Blueprint $table) {
                $table->dropColumn('config_notif');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $schema = Schema::connection('methods');
        if (! $schema->hasTable('qc_method')) {
            return;
        }

        if (! $schema->hasColumn('qc_method', 'config_notif')) {
            $schema->table('qc_method', function (Blueprint $table) {
                $table->integer('config_notif')->default(0)->after('onactive');
            });
        }

        if ($schema->hasColumn('qc_method', 'notify_up_percentage')) {
            DB::connection('methods')
                ->table('qc_method')
                ->update(['config_notif' => DB::raw('notify_up_percentage')]);
        }

        if ($schema->hasColumn('qc_method', 'notify_down_percentage')) {
            $schema->table('qc_method', function (Blueprint $table) {
                $table->dropColumn('notify_down_percentage');
            });
        }

        if ($schema->hasColumn('qc_method', 'notify_up_percentage')) {
            $schema->table('qc_method', function (Blueprint $table) {
                $table->dropColumn('notify_up_percentage');
            });
        }
    }
};
