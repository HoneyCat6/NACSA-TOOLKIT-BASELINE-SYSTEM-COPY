<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Element-level degradation detection (DECISIONS D-36).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('assessment_elements', function (Blueprint $table) {
            $table->tinyInteger('level_delta')->nullable()->after('maturity_level');
        });

        Schema::table('degradation_alerts', function (Blueprint $table) {
            $table->foreignId('ncsb_element_id')->nullable()->after('domain')->constrained()->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('degradation_alerts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ncsb_element_id');
        });

        Schema::table('assessment_elements', function (Blueprint $table) {
            $table->dropColumn('level_delta');
        });
    }
};
