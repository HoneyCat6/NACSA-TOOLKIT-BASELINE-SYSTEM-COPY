<?php

use App\Enums\Band;
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
        Schema::create('simulation_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('baseline_assessment_id')->constrained('assessments')->cascadeOnDelete();
            $table->string('scenario_name');
            $table->text('modified_responses');
            $table->decimal('simulated_score', 4, 3);
            $table->enum('simulated_band', Band::values());
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simulation_sessions');
    }
};
