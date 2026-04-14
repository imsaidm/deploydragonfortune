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
        Schema::create('market_santiments', function (Blueprint $table) {
            $table->id();
            $table->string('metric'); // contoh: daily_active_addresses
            $table->string('slug');   // contoh: bitcoin
            $table->double('value');
            $table->timestamp('api_timestamp'); // Dilepas unique-nya karena satu timestamp bisa punya banyak metrik
            $table->timestamps();

            $table->unique(['api_timestamp', 'metric', 'slug']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('market_santiments');
    }
};
