<?php

namespace App\Services;

use App\Enums\AuditCategory;
use App\Enums\Role;
use App\Exceptions\TemplateValidationException;
use App\Jobs\SendDegradationAlertJob;
use App\Models\Assessment;
use App\Models\AssessmentTrend;
use App\Models\Sector;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
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
        private DeadlineService $deadlines,
        private AuditService $audit,
    ) {}

    /**
     * @param  array<int, int|string|null>  $sectorLevels
     *
     * @throws TemplateValidationException The rejection is audited and nothing is persisted (D-31).
     */
    public function import(
        UploadedFile $file,
        string $cycleLabel,
        CarbonInterface $assessmentDate,
        User $uploader,
        ?int $sectorId = null,
        array $sectorLevels = [],
    ): Assessment {
        $storedPath = null;

        try {
            return DB::transaction(function () use ($file, $cycleLabel, $assessmentDate, $uploader, $sectorId, $sectorLevels, &$storedPath): Assessment {
                $sector = $sectorId === null ? null : Sector::query()->lockForUpdate()->findOrFail($sectorId);
                if ($sector !== null) {
                    $latest = $sector->assessments()->newestFirst()->first();

                    if ($latest !== null && $assessmentDate->lte($latest->assessment_date)) {
                        throw ValidationException::withMessages([
                            'assessment_date' => "The assessment date must be later than the latest cycle, {$latest->cycle_label} ({$latest->assessment_date->format('j M Y')}).",
                        ]);
                    }
                }

                $parsed = $this->parser->parse($file->getRealPath(), $sector?->disabledCoreCodes() ?? []);
                $scored = $this->scoring->score($parsed, $sector, $sectorLevels);
                $storedPath = $file->store('assessments', self::Disk);

                if ($storedPath === false) {
                    throw new RuntimeException('The assessment workbook could not be stored.');
                }
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
                    'sector_id' => $sector?->id,
                ], $uploader);

                $this->deadlines->markMetByUpload($assessment, $uploader);

                return $assessment;
            });
        } catch (Throwable $exception) {
            if ($storedPath) {
                Storage::disk(self::Disk)->delete($storedPath);
            }

            if ($exception instanceof TemplateValidationException) {
                $this->recordRejection($file, $exception->getMessage(), $uploader);
            }

            throw $exception;
        }
    }

    /**
     * D-35: permanently delete an uploaded cycle — its elements, trend, alerts, executive reports and stored files.
     * Deadlines its upload met are reopened (D-46).
     *
     * Only ever called for the latest cycle, so no other cycle's trend points at it.
     */
    public function undo(Assessment $assessment, string $reason, User $user): void
    {
        $reportPaths = $assessment->reports()->pluck('file_path')->all();

        DB::transaction(function () use ($assessment, $reason, $user, $reportPaths): void {
            $deadlinesReopened = $this->deadlines->reopenMetBy($assessment, $user);

            $this->audit->log('assessment.deleted', AuditCategory::Compliance, $assessment, [
                'cycle_label' => $assessment->cycle_label,
                'assessment_date' => $assessment->assessment_date->toDateString(),
                'filename' => $assessment->original_filename,
                'overall_score' => $assessment->overall_score,
                'overall_band' => $assessment->overall_band->value,
                'reports_deleted' => count($reportPaths),
                'deadlines_reopened' => $deadlinesReopened,
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
