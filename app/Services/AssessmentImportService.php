<?php

namespace App\Services;

use App\Enums\AuditCategory;
use App\Enums\Role;
use App\Exceptions\TemplateValidationException;
use App\Jobs\SendDegradationAlertJob;
use App\Models\Assessment;
use App\Models\AssessmentTrend;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Upload → parse → score → persist → trend → alert, processed synchronously (TECH_SPEC §3.2, DECISIONS D-30).
 */
class AssessmentImportService
{
    public const Disk = 'local';

    public function __construct(
        private ExcelParserService $parser,
        private ScoringService $scoring,
        private TrendService $trends,
        private AuditService $audit,
    ) {}

    /**
     * @throws TemplateValidationException The rejection is audited and nothing is persisted (D-31).
     */
    public function import(UploadedFile $file, string $cycleLabel, CarbonInterface $assessmentDate, User $uploader): Assessment
    {
        try {
            $scored = $this->scoring->score($this->parser->parse($file->getRealPath()));
        } catch (TemplateValidationException $exception) {
            $this->recordRejection($file, $exception->getMessage(), $uploader);

            throw $exception;
        }

        $storedPath = $file->store('assessments', self::Disk);

        try {
            return DB::transaction(function () use ($scored, $file, $cycleLabel, $assessmentDate, $uploader, $storedPath): Assessment {
                $assessment = $this->scoring->persist($scored, [
                    'created_by' => $uploader->id,
                    'assessment_date' => $assessmentDate,
                    'cycle_label' => $cycleLabel,
                    'original_filename' => $file->getClientOriginalName(),
                    'stored_path' => $storedPath,
                    'file_hash' => hash_file('sha256', $file->getRealPath()),
                ]);

                $trend = $this->trends->computeTrend($assessment);

                if ($trend !== null && $this->trends->detectDegradation($trend)->isNotEmpty()) {
                    $this->notifyComplianceOfficers($trend);
                }

                $this->audit->log('assessment.upload', AuditCategory::Compliance, $assessment, [
                    'cycle_label' => $cycleLabel,
                    'filename' => $file->getClientOriginalName(),
                    'overall_band' => $assessment->overall_band->value,
                ], $uploader);

                return $assessment;
            });
        } catch (Throwable $exception) {
            Storage::disk(self::Disk)->delete($storedPath);

            throw $exception;
        }
    }

    /**
     * D-35: permanently delete an uploaded cycle — its elements, trend, alerts, executive reports and stored files.
     *
     * Only ever called for the latest cycle, so no other cycle's trend points at it.
     */
    public function undo(Assessment $assessment, string $reason, User $user): void
    {
        $reportPaths = $assessment->reports()->pluck('file_path')->all();

        DB::transaction(function () use ($assessment, $reason, $user, $reportPaths): void {
            $this->audit->log('assessment.deleted', AuditCategory::Compliance, $assessment, [
                'cycle_label' => $assessment->cycle_label,
                'assessment_date' => $assessment->assessment_date->toDateString(),
                'filename' => $assessment->original_filename,
                'overall_score' => $assessment->overall_score,
                'overall_band' => $assessment->overall_band->value,
                'reports_deleted' => count($reportPaths),
                'reason' => $reason,
            ], $user);

            $assessment->delete();
        });

        Storage::disk(self::Disk)->delete($assessment->stored_path);
        Storage::disk(ReportService::Disk)->delete($reportPaths);
    }

    /**
     * D-31: rejected uploads are not persisted as assessments, only audited.
     */
    public function recordRejection(UploadedFile $file, string $reason, ?User $uploader = null): void
    {
        $this->audit->log('assessment.rejected', AuditCategory::Compliance, metadata: [
            'filename' => $file->getClientOriginalName(),
            'reason' => $reason,
        ], user: $uploader);
    }

    /**
     * D-34: every active Compliance Officer is emailed, once the upload has committed.
     */
    private function notifyComplianceOfficers(AssessmentTrend $trend): void
    {
        User::query()
            ->where('role', Role::ComplianceOfficer)
            ->where('is_active', true)
            ->get()
            ->each(fn (User $officer) => SendDegradationAlertJob::dispatch($trend, $officer)->afterCommit());
    }
}
