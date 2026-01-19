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
        Schema::table('results', function (Blueprint $table) {
            $table->index('athlete_id');
            $table->index('discipline_id');
            $table->index('event_id');
            $table->index('athlete_category_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('results', function (Blueprint $table) {
            $table->dropIndex(['athlete_id']);
            $table->dropIndex(['discipline_id']);
            $table->dropIndex(['event_id']);
            $table->dropIndex(['athlete_category_id']);
        });
    }
};
