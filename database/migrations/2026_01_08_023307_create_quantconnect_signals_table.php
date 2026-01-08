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
        Schema::create('quantconnect_signals', function (Blueprint $table) {
            $table->id();
            $table->integer('project_id')->index();
            $table->string('project_name')->nullable();
            $table->enum('signal_type', ['entry', 'exit', 'update', 'error'])->index();
            $table->string('symbol', 50)->index();
            $table->enum('action', ['buy', 'sell', 'long', 'short']);
            $table->decimal('price', 20, 8);
            $table->decimal('quantity', 20, 8)->nullable();
            $table->decimal('target_price', 20, 8)->nullable();
            $table->decimal('stop_loss', 20, 8)->nullable();
            $table->decimal('realized_pnl', 20, 8)->nullable();
            $table->text('message')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamp('webhook_received_at');
            $table->timestamp('signal_timestamp')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quantconnect_signals');
    }
};
