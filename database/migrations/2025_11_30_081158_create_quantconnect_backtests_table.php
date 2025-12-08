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
        Schema::create('quantconnect_backtests', function (Blueprint $table) {
            $table->id();
            $table->string('backtest_id', 100)->unique()->index();
            $table->string('project_id', 100)->nullable()->index();
            $table->string('name', 255);
            $table->string('strategy_type', 100)->nullable();
            $table->text('description')->nullable();
            
            // Dates
            $table->timestamp('backtest_start')->nullable();
            $table->timestamp('backtest_end')->nullable();
            $table->integer('duration_days')->nullable();
            
            // Performance Metrics
            $table->decimal('total_return', 12, 4)->nullable();
            $table->decimal('cagr', 10, 4)->nullable();
            $table->decimal('sharpe_ratio', 8, 4)->nullable();
            $table->decimal('sortino_ratio', 8, 4)->nullable();
            $table->decimal('calmar_ratio', 8, 4)->nullable();
            $table->decimal('max_drawdown', 10, 4)->nullable();
            $table->integer('recovery_days')->nullable();
            
            // Trade Statistics
            $table->integer('total_trades')->default(0);
            $table->integer('winning_trades')->default(0);
            $table->integer('losing_trades')->default(0);
            $table->decimal('win_rate', 8, 4)->nullable();
            $table->decimal('profit_factor', 10, 4)->nullable();
            $table->decimal('expectancy', 15, 4)->nullable();
            $table->decimal('avg_win', 10, 4)->nullable();
            $table->decimal('avg_loss', 10, 4)->nullable();
            $table->decimal('largest_win', 15, 4)->nullable();
            $table->decimal('largest_loss', 15, 4)->nullable();
            $table->integer('longest_win_streak')->nullable();
            $table->integer('longest_loss_streak')->nullable();
            
            // Financial
            $table->decimal('starting_capital', 18, 2)->nullable();
            $table->decimal('ending_capital', 18, 2)->nullable();
            $table->decimal('total_fees', 15, 4)->nullable();
            
            // Data payloads (JSON)
            $table->json('equity_curve')->nullable();
            $table->json('monthly_returns')->nullable();
            $table->json('trades')->nullable();
            $table->json('parameters')->nullable();
            $table->json('raw_result')->nullable();
            
            // Meta
            $table->string('status', 50)->default('completed');
            $table->string('import_source', 50)->default('manual'); // manual, api, webhook
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quantconnect_backtests');
    }
};
