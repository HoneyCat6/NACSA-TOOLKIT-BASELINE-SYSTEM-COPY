<?php

use App\Enums\NotificationType;
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
        Schema::create('deadline_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compliance_deadline_id')->constrained()->cascadeOnDelete();
            $table->enum('type', array_column(NotificationType::cases(), 'value'));
            $table->unsignedSmallInteger('lead_day')->default(0);
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('dispatched_at');
            $table->boolean('delivered')->default(false);
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->unique(['compliance_deadline_id', 'type', 'lead_day'], 'deadline_dispatch_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deadline_notifications');
    }
};
