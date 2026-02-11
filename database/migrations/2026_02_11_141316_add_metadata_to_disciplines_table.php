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
        Schema::table('disciplines', function (Blueprint $table) {
            $table->string('discipline_type')->nullable()->after('type');
            $table->boolean('is_official_wa')->default(false)->after('discipline_type');
            $table->boolean('is_official_swa')->default(false)->after('is_official_wa');
            $table->foreignId('discipline_parent_id')->nullable()->after('is_official_swa')->constrained('disciplines')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('disciplines', function (Blueprint $table) {
            $table->dropForeign(['discipline_parent_id']);
            $table->dropColumn(['discipline_type', 'is_official_wa', 'is_official_swa', 'discipline_parent_id']);
        });
    }
};
