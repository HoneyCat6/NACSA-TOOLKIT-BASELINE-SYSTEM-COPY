<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reminders re-arm when a deadline's due date changes (DECISIONS D-44), and the deadline records who acknowledged it.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('deadline_notifications', function (Blueprint $table) {
            $table->date('due_date')->nullable()->after('lead_day');
        });

        DB::table('deadline_notifications')
            ->join('compliance_deadlines', 'compliance_deadlines.id', '=', 'deadline_notifications.compliance_deadline_id')
            ->update(['deadline_notifications.due_date' => DB::raw('compliance_deadlines.due_date')]);

        Schema::table('deadline_notifications', function (Blueprint $table) {
            $table->date('due_date')->nullable(false)->change();

            // The new key also leads with compliance_deadline_id, so the foreign key keeps an index while the old one is dropped.
            $table->unique(['compliance_deadline_id', 'type', 'lead_day', 'due_date'], 'deadline_dispatch_due_unique');
            $table->dropUnique('deadline_dispatch_unique');
        });

        Schema::table('compliance_deadlines', function (Blueprint $table) {
            $table->foreignId('acknowledged_by')->nullable()->after('acknowledged_at')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('compliance_deadlines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('acknowledged_by');
        });

        Schema::table('deadline_notifications', function (Blueprint $table) {
            $table->unique(['compliance_deadline_id', 'type', 'lead_day'], 'deadline_dispatch_unique');
            $table->dropUnique('deadline_dispatch_due_unique');
            $table->dropColumn('due_date');
        });
    }
};
