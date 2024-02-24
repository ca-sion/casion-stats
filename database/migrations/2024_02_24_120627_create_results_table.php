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
        Schema::create('results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('athlete_id');
            $table->foreignId('discipline_id');
            $table->foreignId('event_id');
            $table->foreignId('athlete_category_id');
            $table->string('performance');
            $table->string('rank')->nullable();
            $table->string('wind')->nullable();
            $table->string('information')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('results');
    }
};
