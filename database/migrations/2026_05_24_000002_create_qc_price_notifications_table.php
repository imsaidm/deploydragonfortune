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
        if ($schema->hasTable('qc_price_notifications')) {
            return;
        }

        $schema->create('qc_price_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('qc_signal_id')->index();
            $table->unsignedBigInteger('id_method')->index();
            $table->string('direction', 8);
            $table->decimal('step_percentage', 8, 4);
            $table->decimal('level_percentage', 8, 4);
            $table->decimal('entry_price', 20, 8);
            $table->decimal('market_price', 20, 8);
            $table->decimal('movement_percentage', 12, 6);
            $table->string('source', 50)->nullable();
            $table->string('event_uid', 100)->nullable();
            $table->timestamp('telegram_sent_at')->nullable();
            $table->json('telegram_response')->nullable();
            $table->timestamps();

            $table->unique('event_uid', 'qc_price_notifications_event_uid_unique');
            $table->index(
                ['qc_signal_id', 'direction', 'level_percentage'],
                'qc_price_notifications_signal_level_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('methods')->dropIfExists('qc_price_notifications');
    }
};
