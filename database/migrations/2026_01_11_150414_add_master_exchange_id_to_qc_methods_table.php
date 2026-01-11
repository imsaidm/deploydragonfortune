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
            // Add foreign key to master_exchanges
            $table->foreignId('master_exchange_id')
                  ->nullable()
                  ->after('exchange')
                  ->constrained('master_exchanges')
                  ->onDelete('set null')
                  ->comment('Reference to master exchange account');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('qc_methods', function (Blueprint $table) {
            $table->dropForeign(['master_exchange_id']);
            $table->dropColumn('master_exchange_id');
        });
    }
};
