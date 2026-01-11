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
        Schema::table('master_exchanges', function (Blueprint $table) {
            $table->enum('market_type', ['SPOT', 'FUTURES'])
                  ->default('FUTURES')
                  ->after('exchange_type')
                  ->comment('Market type for Binance (SPOT or FUTURES)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('master_exchanges', function (Blueprint $table) {
            $table->dropColumn('market_type');
        });
    }
};
