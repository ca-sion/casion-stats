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
            $table->renameColumn('name', 'name_fr');
            $table->string('name_de')->nullable()->after('id'); // name_de after id for logical group
            $table->string('name_en')->nullable()->after('name_fr');
            $table->string('code')->nullable()->after('name_en');
            $table->string('wa_code')->nullable()->after('code');
            $table->string('seltec_code')->nullable()->after('wa_code');
            $table->boolean('has_wind')->default(false)->after('seltec_code');
            $table->string('type')->nullable()->after('has_wind');
            $table->boolean('is_relay')->default(false)->after('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('disciplines', function (Blueprint $table) {
            $table->dropColumn([
                'name_de',
                'name_en',
                'code',
                'wa_code',
                'seltec_code',
                'has_wind',
                'type',
                'is_relay'
            ]);
            $table->renameColumn('name_fr', 'name');
        });
    }
};
