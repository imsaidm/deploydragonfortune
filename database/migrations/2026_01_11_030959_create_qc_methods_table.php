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
        Schema::create('qc_methods', function (Blueprint $table) {
            $table->id();
            
            // Basic Information
            $table->string('nama_metode', 150)->unique()->comment('Method name');
            $table->enum('market_type', ['SPOT', 'FUTURES'])->comment('Market type');
            $table->string('pair', 30)->comment('Trading pair (e.g., BTCUSDT)');
            $table->string('tf', 30)->comment('Timeframe (e.g., 1h, 4h)');
            $table->string('exchange', 30)->default('BINANCE')->comment('Exchange name');
            
            // Performance Metrics (from training/backtest)
            $table->decimal('cagr', 12, 6)->nullable()->comment('Compound Annual Growth Rate');
            $table->decimal('drawdown', 12, 6)->nullable()->comment('Maximum drawdown');
            $table->decimal('winrate', 12, 6)->nullable()->comment('Win rate percentage');
            $table->decimal('lossrate', 12, 6)->nullable()->comment('Loss rate percentage');
            $table->decimal('prob_sr', 12, 6)->nullable()->comment('Probability of success');
            $table->decimal('sharpen_ratio', 12, 6)->nullable()->comment('Sharpe ratio');
            $table->decimal('sortino_ratio', 12, 6)->nullable()->comment('Sortino ratio');
            $table->decimal('information_ratio', 12, 6)->nullable()->comment('Information ratio');
            $table->decimal('turnover', 12, 6)->nullable()->comment('Portfolio turnover');
            $table->decimal('total_orders', 12, 6)->nullable()->comment('Total number of orders');
            $table->json('kpi_extra')->nullable()->comment('Additional KPI metrics');
            
            // QuantConnect Integration
            $table->text('qc_url')->comment('QuantConnect backtest URL');
            $table->string('qc_project_id', 100)->nullable()->comment('QuantConnect project ID');
            $table->string('webhook_token', 255)->nullable()->comment('Unique webhook token for this method');
            
            // Binance Integration
            $table->text('api_key')->nullable()->comment('Binance API Key (encrypted)');
            $table->text('secret_key')->nullable()->comment('Binance Secret Key (encrypted)');
            
            // Risk Management
            $table->json('risk_settings')->nullable()->comment('Risk management parameters');
            
            // Status & Monitoring
            $table->boolean('is_active')->default(true)->comment('Method active status');
            $table->boolean('auto_trade_enabled')->default(false)->comment('Auto-trade to Binance enabled');
            $table->timestamp('last_signal_at')->nullable()->comment('Last signal received timestamp');
            
            $table->timestamps();
            
            // Indexes
            $table->index('market_type');
            $table->index('is_active');
            $table->index('pair');
            $table->index(['is_active', 'auto_trade_enabled']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('qc_methods');
    }
};
