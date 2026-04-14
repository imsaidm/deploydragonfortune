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
        Schema::create('market_alternatives', function (Blueprint $table) {
            $table->id();
            $table->integer('value');
            $table->string('value_classification'); // contoh: "Fear", "Greed"
            $table->timestamp('api_timestamp')->unique(); // Menggunakan unique agar tidak ada data ganda
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('market_alternatives');
    }
};
