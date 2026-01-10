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
            $table->string('qc_id')->index()->comment('QuantConnect Algorithm ID');
            $table->enum('type', ['REMINDER', 'SIGNAL'])->comment('Notification type');
            $table->enum('market_type', ['SPOT', 'FUTURES'])->comment('Market type');
            $table->string('symbol')->comment('Trading symbol e.g., BTCUSDT');
            $table->enum('side', ['BUY', 'SELL'])->nullable()->comment('Trade direction');
            $table->decimal('price', 20, 8)->nullable()->comment('Entry price');
            $table->decimal('tp', 20, 8)->nullable()->comment('Take Profit');
            $table->decimal('sl', 20, 8)->nullable()->comment('Stop Loss');
            $table->integer('leverage')->nullable()->comment('Leverage for futures');
            $table->decimal('margin_usd', 20, 8)->nullable()->comment('Margin in USD');
            $table->decimal('quantity', 20, 8)->nullable()->comment('Trade quantity');
            $table->text('message')->comment('Reminder/signal message');
            $table->boolean('telegram_sent')->default(false)->comment('Telegram delivery status');
            $table->timestamp('telegram_sent_at')->nullable();
            $table->text('telegram_response')->nullable()->comment('Telegram API response');
            $table->timestamps();
            
            // Indexes for better query performance
            $table->index(['qc_id', 'type']);
            $table->index(['market_type', 'symbol']);
            $table->index('telegram_sent');
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
