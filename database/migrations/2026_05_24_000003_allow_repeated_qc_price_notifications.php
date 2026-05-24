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
        if (! $schema->hasTable('qc_price_notifications')) {
            return;
        }

        $schema->table('qc_price_notifications', function (Blueprint $table) use ($schema) {
            if (! $schema->hasColumn('qc_price_notifications', 'event_uid')) {
                $table->string('event_uid', 100)->nullable()->after('source');
            }
        });

        if ($schema->hasIndex('qc_price_notifications', 'qc_price_notifications_unique_level')) {
            $schema->table('qc_price_notifications', function (Blueprint $table) {
                $table->dropUnique('qc_price_notifications_unique_level');
            });
        }

        if (! $schema->hasIndex('qc_price_notifications', 'qc_price_notifications_event_uid_unique')) {
            $schema->table('qc_price_notifications', function (Blueprint $table) {
                $table->unique('event_uid', 'qc_price_notifications_event_uid_unique');
            });
        }

        if (! $schema->hasIndex('qc_price_notifications', 'qc_price_notifications_signal_level_index')) {
            $schema->table('qc_price_notifications', function (Blueprint $table) {
                $table->index(
                    ['qc_signal_id', 'direction', 'level_percentage'],
                    'qc_price_notifications_signal_level_index'
                );
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $schema = Schema::connection('methods');
        if (! $schema->hasTable('qc_price_notifications')) {
            return;
        }

        if ($schema->hasIndex('qc_price_notifications', 'qc_price_notifications_signal_level_index')) {
            $schema->table('qc_price_notifications', function (Blueprint $table) {
                $table->dropIndex('qc_price_notifications_signal_level_index');
            });
        }

        if ($schema->hasIndex('qc_price_notifications', 'qc_price_notifications_event_uid_unique')) {
            $schema->table('qc_price_notifications', function (Blueprint $table) {
                $table->dropUnique('qc_price_notifications_event_uid_unique');
            });
        }

        if (! $schema->hasIndex('qc_price_notifications', 'qc_price_notifications_unique_level')) {
            $schema->table('qc_price_notifications', function (Blueprint $table) {
                $table->unique(
                    ['qc_signal_id', 'direction', 'level_percentage'],
                    'qc_price_notifications_unique_level'
                );
            });
        }

        $schema->table('qc_price_notifications', function (Blueprint $table) use ($schema) {
            if ($schema->hasColumn('qc_price_notifications', 'event_uid')) {
                $table->dropColumn('event_uid');
            }
        });
    }
};
