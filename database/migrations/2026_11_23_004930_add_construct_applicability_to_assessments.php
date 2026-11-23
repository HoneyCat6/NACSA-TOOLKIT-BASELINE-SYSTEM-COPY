<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const OriginalBands = ['Initial', 'Basic', 'Intermediate', 'Advanced'];

    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->foreignId('sector_id')->nullable()->constrained()->restrictOnDelete();
            $table->index(['sector_id', 'assessment_date']);
            $table->enum('overall_band', [...self::OriginalBands, 'N/A'])->change();
            $table->decimal('overall_score', 4, 3)->nullable()->change();
        });

        Schema::table('assessment_elements', function (Blueprint $table) {
            $table->enum('status', ['scored', 'unanswered', 'disabled'])->default('scored')->change();
        });

        Schema::table('simulation_sessions', function (Blueprint $table) {
            $table->enum('simulated_band', [...self::OriginalBands, 'N/A'])->change();
            $table->decimal('simulated_score', 4, 3)->nullable()->change();
        });

        Schema::create('sector_construct_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sector_construct_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('maturity_level')->nullable();
            $table->timestamps();
            $table->unique(['assessment_id', 'sector_construct_id']);
        });

        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER protect_ncsb_elements BEFORE DELETE ON ncsb_elements BEGIN SELECT RAISE(ABORT, 'NCSB core elements cannot be deleted'); END");
        } else {
            DB::unprepared("CREATE TRIGGER protect_ncsb_elements BEFORE DELETE ON ncsb_elements FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'NCSB core elements cannot be deleted'");
        }
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS protect_ncsb_elements');

        Schema::dropIfExists('sector_construct_scores');

        DB::table('simulation_sessions')->where('simulated_band', 'N/A')->update(['simulated_band' => 'Initial', 'simulated_score' => 0]);
        DB::table('assessment_elements')->where('status', 'disabled')->update(['status' => 'unanswered']);
        DB::table('assessments')->where('overall_band', 'N/A')->update(['overall_band' => 'Initial', 'overall_score' => 0]);

        Schema::table('simulation_sessions', function (Blueprint $table) {
            $table->enum('simulated_band', self::OriginalBands)->change();
            $table->decimal('simulated_score', 4, 3)->change();
        });
        Schema::table('assessment_elements', function (Blueprint $table) {
            $table->enum('status', ['scored', 'unanswered'])->default('scored')->change();
        });
        Schema::table('assessments', function (Blueprint $table) {
            $table->dropForeign(['sector_id']);
            $table->dropIndex(['sector_id', 'assessment_date']);
            $table->dropColumn('sector_id');
            $table->enum('overall_band', self::OriginalBands)->change();
            $table->decimal('overall_score', 4, 3)->change();
        });
    }
};
