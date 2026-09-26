<?php

use App\Enums\Domain;
use App\Enums\ElementStatus;
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
        Schema::create('assessment_elements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ncsb_element_id')->constrained()->restrictOnDelete();
            $table->enum('domain', Domain::values());
            $table->string('element_code', 10);
            $table->unsignedTinyInteger('yes_count')->default(0);
            $table->unsignedTinyInteger('maturity_level')->nullable();
            $table->enum('status', array_column(ElementStatus::cases(), 'value'))->default(ElementStatus::Scored->value);
            $table->text('responses');
            $table->timestamps();

            $table->unique(['assessment_id', 'element_code']);
            $table->index(['assessment_id', 'domain']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessment_elements');
    }
};
