<?php

use App\Enums\Band;
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
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->date('assessment_date')->index();
            $table->string('cycle_label', 50)->unique();
            $table->string('original_filename');
            $table->string('stored_path');
            $table->char('file_hash', 64)->unique();
            $table->decimal('overall_score', 4, 3);
            $table->enum('overall_band', Band::values());

            foreach (Domain::cases() as $domain) {
                $table->decimal($domain->scoreColumn(), 3, 2)->nullable();
            }

            $table->unsignedTinyInteger('unanswered_count')->default(0);
            $table->unsignedSmallInteger('defaulted_cell_count')->default(0);
            $table->json('parse_warnings')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};
