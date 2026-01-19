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
            $table->string('license')->nullable()->unique()->after('genre');
        });

        Schema::table('athlete_categories', function (Blueprint $table) {
            $table->string('name_de')->nullable()->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('athletes', function (Blueprint $table) {
            $table->dropColumn('license');
        });

        Schema::table('athlete_categories', function (Blueprint $table) {
            $table->dropColumn('name_de');
        });
    }
};
