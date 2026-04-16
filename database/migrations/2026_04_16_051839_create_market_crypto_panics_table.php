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
        Schema::create('market_crypto_panics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('panic_id')->unique(); // ID unik dari CryptoPanic
            $table->string('title', 500);
            $table->string('domain');
            $table->text('url')->nullable(); // Menggunakan text untuk jaga-jaga URL panjang
            
            // Fitur Enterprise: Panic Score
            $table->decimal('panic_score', 8, 4)->nullable(); 
            
            // Metrik Sentimen (Votes)
            $table->integer('votes_positive')->default(0);
            $table->integer('votes_negative')->default(0);
            $table->integer('votes_important')->default(0);
            $table->integer('votes_liked')->default(0);
            $table->integer('votes_disliked')->default(0);
            $table->integer('votes_lol')->default(0);
            $table->integer('votes_toxic')->default(0);
            $table->integer('votes_save')->default(0);
            
            $table->string('currencies')->nullable(); // Contoh: "BTC,ETH"
            $table->timestamp('published_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('market_crypto_panics');
    }
};
