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
        // Drop old table
        Schema::dropIfExists('quantconnect_signals');
        
        // Create qc_signal table
        Schema::create('qc_signal', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('id_method');
            $table->dateTime('datetime');
            $table->string('type', 10);
            $table->string('jenis', 10);
            $table->decimal('price_entry', 20, 8)->default(0);
            $table->decimal('price_exit', 20, 8)->default(0);
            $table->decimal('target_tp', 20, 8)->default(0);
            $table->decimal('target_sl', 20, 8)->default(0);
            $table->decimal('real_tp', 20, 8)->default(0);
            $table->decimal('real_sl', 20, 8)->default(0);
            $table->text('message');
            
            // Telegram tracking columns
            $table->boolean('telegram_sent')->default(false);
            $table->timestamp('telegram_sent_at')->nullable();
            $table->text('telegram_response')->nullable();
            
            $table->timestamps();
        });
        
        // Create qc_reminders table
        Schema::create('qc_reminders', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('id_method');
            $table->dateTime('datetime');
            $table->text('message');
            
            // Telegram tracking columns
            $table->boolean('telegram_sent')->default(false);
            $table->timestamp('telegram_sent_at')->nullable();
            $table->text('telegram_response')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('qc_signal');
        Schema::dropIfExists('qc_reminders');
    }
};
