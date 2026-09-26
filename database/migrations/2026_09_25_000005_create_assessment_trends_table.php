<?php

use App\Enums\Domain;
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
        Schema::create('assessment_trends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('prev_assessment_id')->constrained('assessments')->cascadeOnDelete();

            foreach (Domain::cases() as $domain) {
                $table->decimal($domain->deltaColumn(), 4, 2)->nullable();
            }

            $table->decimal('overall_delta', 4, 3);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessment_trends');
    }
};
