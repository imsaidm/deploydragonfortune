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
        Schema::create('master_exchanges', function (Blueprint $table) {
            $table->id();
            
            // Basic Information
            $table->string('name', 100)->unique()->comment('Display name (e.g., "Binance Main Account")');
            $table->enum('exchange_type', ['BINANCE', 'BYBIT', 'OKX'])->default('BINANCE')->comment('Exchange platform');
            
            // API Credentials (Encrypted)
            $table->text('api_key')->comment('Encrypted API key');
            $table->text('secret_key')->comment('Encrypted secret key');
            
            // Optional Settings
            $table->boolean('testnet')->default(false)->comment('Is this a testnet account?');
            $table->text('description')->nullable()->comment('Notes about this account');
            
            // Status & Monitoring
            $table->boolean('is_active')->default(true)->comment('Account active status');
            $table->timestamp('last_validated_at')->nullable()->comment('Last successful API validation');
            
            $table->timestamps();
            
            // Indexes
            $table->index('exchange_type');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('master_exchanges');
    }
};
