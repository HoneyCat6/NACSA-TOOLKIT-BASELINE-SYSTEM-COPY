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
        Schema::create('degradation_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_trend_id')->constrained()->cascadeOnDelete();
            $table->enum('domain', Domain::values());
            $table->decimal('score_drop', 4, 2);
            $table->boolean('acknowledged')->default(false);
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('triggered_at');
            $table->timestamps();

            $table->index(['acknowledged', 'triggered_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('degradation_alerts');
    }
};
