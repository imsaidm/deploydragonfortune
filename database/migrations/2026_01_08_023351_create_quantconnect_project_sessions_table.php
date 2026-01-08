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
        Schema::create('quantconnect_project_sessions', function (Blueprint $table) {
            $table->id();
            $table->integer('project_id')->unique()->index();
            $table->string('project_name');
            $table->boolean('is_live')->default(false)->index();
            $table->enum('status', ['active', 'stopped', 'error'])->default('active');
            $table->timestamp('last_signal_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quantconnect_project_sessions');
    }
};
