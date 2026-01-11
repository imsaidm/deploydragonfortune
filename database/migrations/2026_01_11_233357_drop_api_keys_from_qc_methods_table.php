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
        Schema::table('qc_methods', function (Blueprint $table) {
            $table->dropColumn(['api_key', 'secret_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('qc_methods', function (Blueprint $table) {
            $table->text('api_key')->nullable()->comment('Encrypted Binance API key');
            $table->text('secret_key')->nullable()->comment('Encrypted Binance secret key');
        });
    }
};
