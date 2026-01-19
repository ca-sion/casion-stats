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
        Schema::table('athletes', function (Blueprint $table) {
            $table->string('genre', 1)->nullable()->change();
        });

        Schema::table('athlete_categories', function (Blueprint $table) {
            $table->string('genre', 1)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('athletes', function (Blueprint $table) {
            $table->tinyText('genre')->nullable()->change();
        });

        Schema::table('athlete_categories', function (Blueprint $table) {
            $table->tinyText('genre')->nullable()->change();
        });
    }
};
