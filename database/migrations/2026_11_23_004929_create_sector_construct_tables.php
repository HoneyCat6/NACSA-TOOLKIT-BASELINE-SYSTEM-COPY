<?php

use App\Enums\Domain;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sectors', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->string('code_of_practice')->nullable();
            $table->timestamps();
        });

        Schema::create('sector_core_constructs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sector_id')->constrained()->restrictOnDelete();
            $table->foreignId('ncsb_element_id')->constrained()->restrictOnDelete();
            $table->enum('status', ['active', 'disabled']);
            $table->string('reason', 500)->nullable();
            $table->timestamps();
            $table->unique(['sector_id', 'ncsb_element_id']);
        });

        Schema::create('sector_constructs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sector_id')->constrained()->restrictOnDelete();
            $table->string('code', 20);
            $table->string('name', 150);
            $table->enum('domain', Domain::values());
            $table->text('requirement');
            $table->timestamps();
            $table->unique(['sector_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sector_constructs');
        Schema::dropIfExists('sector_core_constructs');
        Schema::dropIfExists('sectors');
    }
};
