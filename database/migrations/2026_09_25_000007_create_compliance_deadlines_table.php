<?php

use App\Enums\DeadlineStatus;
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
        Schema::create('compliance_deadlines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('officer_id')->constrained('users')->restrictOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('due_date');
            $table->json('lead_days');
            $table->foreignId('secondary_contact_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', DeadlineStatus::values())->default(DeadlineStatus::Upcoming->value);
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('met_at')->nullable();
            $table->foreignId('met_by_assessment_id')->nullable()->constrained('assessments')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'due_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compliance_deadlines');
    }
};
