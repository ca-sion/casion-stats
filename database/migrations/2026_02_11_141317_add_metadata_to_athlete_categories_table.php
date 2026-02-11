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
        Schema::table('athlete_categories', function (Blueprint $table) {
            $table->boolean('is_youth')->default(false)->after('genre');
            $table->boolean('is_primary')->default(false)->after('is_youth');
            $table->integer('age_start')->nullable()->after('is_primary');
            $table->string('name_fr')->nullable()->after('age_start');
            $table->string('name_en')->nullable()->after('name_fr');
            $table->boolean('is_official_wa')->default(false)->after('name_en');
            $table->boolean('is_official_swa')->default(false)->after('is_official_wa');
            $table->string('type')->nullable()->after('is_official_swa');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('athlete_categories', function (Blueprint $table) {
            $table->dropColumn(['is_youth', 'is_primary', 'age_start', 'name_fr', 'name_en', 'is_official_wa', 'is_official_swa', 'type']);
        });
    }
};
