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
        Schema::create('ncsb_elements', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->enum('domain', Domain::values());
            $table->string('category');
            $table->string('name');
            $table->text('description');
            $table->unsignedSmallInteger('first_row');
            $table->unsignedSmallInteger('last_row');
            $table->unsignedTinyInteger('question_count');
            $table->unsignedTinyInteger('threshold_l1');
            $table->unsignedTinyInteger('threshold_l2');
            $table->unsignedTinyInteger('threshold_l3');
            $table->text('requirement_l1');
            $table->text('requirement_l2');
            $table->text('requirement_l3');
            $table->json('questions');
            $table->unsignedTinyInteger('domain_risk_weight')->default(1);
            $table->unsignedSmallInteger('sort_order');
            $table->timestamps();

            $table->index(['domain', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ncsb_elements');
    }
};
