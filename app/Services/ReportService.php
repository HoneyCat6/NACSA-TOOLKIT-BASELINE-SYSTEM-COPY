<?php

namespace App\Services;

use App\Enums\AuditCategory;
use App\Enums\ComplianceStatus;
use App\Enums\Domain;
use App\Enums\ElementStatus;
use App\Enums\MaturityLevel;
use App\Enums\RiskCategory;
use App\Models\Assessment;
use App\Models\AssessmentElement;
use App\Models\ExecutiveReport;
use App\Models\SectorConstructScore;
use App\Models\Setting;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Executive summary generator (M2, FR-30 – FR-35): risk translation, narratives, remediation ranking and the board PDF.
 *
 * Read-only consumer of assessment data (TECH_SPEC §3.3).
 */
class ReportService
{
    public const Disk = 'local';

    public const ActionLimit = 10;

    public function __construct(private AuditService $audit) {}

    /**
     * Render and store a new report version. Earlier versions are never touched (DECISIONS D-55, D-56).
     */
    public function generate(Assessment $assessment, User $generatedBy): ExecutiveReport
    {
        $startedAt = hrtime(true);
        $generatedAt = now();
        $payload = $this->buildPayload($assessment, $generatedBy, $generatedAt);

        $path = 'reports/'.Str::uuid().'.pdf';
        Storage::disk(self::Disk)->put($path, $this->render($payload));

        $durationMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);

        try {
            return DB::transaction(function () use ($assessment, $generatedBy, $generatedAt, $payload, $path, $durationMs): ExecutiveReport {
                $report = $assessment->reports()->create([
                    'generated_by' => $generatedBy->id,
                    'file_path' => $path,
                    'payload' => $payload,
                    'generated_at' => $generatedAt,
                ]);

                $this->audit->log('report.generate', AuditCategory::Compliance, $report, [
                    'cycle_label' => $assessment->cycle_label,
                    'version' => $payload['version'],
                    'compliance_status' => $payload['compliance_status'],
                    'duration_ms' => $durationMs,
                ], $generatedBy);

                return $report;
            });
        } catch (Throwable $exception) {
            Storage::disk(self::Disk)->delete($path);

            throw $exception;
        }
    }

    /**
     * Render the PDF from the payload alone. Only the glyphs used are embedded, keeping the file small.
     *
     * @param  array<string, mixed>  $payload
     */
    public function render(array $payload): string
    {
        return Pdf::loadView('reports.pdf', ['report' => $payload])
            ->setPaper('a4')
            ->setOption('enable_font_subsetting', true)
            ->output();
    }

    /**
     * Everything the PDF shows, stored encrypted with the report so it can be reproduced exactly (PRD §9.1).
     *
     * @return array{
     *     version: int,
     *     organisation: string,
     *     footer: ?string,
     *     cycle_label: string,
     *     assessment_date: string,
     *     overall_score: string,
     *     overall_band: string,
     *     compliance_status: string,
     *     domains: list<array{domain: string, area: string, score: ?float, risk: string, narrative: string, change: ?string, applicable: bool}>,
     *     actions: list<array{rank: int, code: string, name: string, area: string, level: int, level_label: string, target_label: string, gap: int, action: string}>,
     *     unanswered: list<array{code: string, name: string}>,
     *     sector: ?string,
     *     sector_maturity_average: ?float,
     *     sector_constructs: list<array{code: string, name: string, domain: string, level: ?int, level_label: string}>,
     *     disabled_core_codes: list<string>,
     *     generated_by: string,
     *     generated_at: string,
     * }
     */
    public function buildPayload(Assessment $assessment, User $generatedBy, CarbonInterface $generatedAt): array
    {
        $assessment->loadMissing(['elements.ncsbElement', 'trend', 'sector', 'sectorConstructScores.sectorConstruct']);

        $domains = $this->riskProfile($assessment);

        return [
            'version' => $assessment->reports()->count() + 1,
            'organisation' => Setting::valueOf(Setting::OrganisationName) ?? config('app.name'),
            'footer' => Setting::valueOf(Setting::ReportFooter),
            'cycle_label' => $assessment->cycle_label,
            'assessment_date' => $assessment->assessment_date->toDateString(),
            'overall_score' => $assessment->overallScoreLabel(),
            'overall_band' => $assessment->overall_band->value,
            'compliance_status' => $this->complianceStatus($assessment, $domains)->value,
            'domains' => $domains,
            'actions' => $this->prioritise($assessment),
            'unanswered' => $assessment->elements
                ->filter(fn (AssessmentElement $element): bool => $element->status === ElementStatus::Unanswered)
                ->sortBy(fn (AssessmentElement $element): int => $element->ncsbElement->sort_order)
                ->map(fn (AssessmentElement $element): array => [
                    'code' => $element->element_code,
                    'name' => $element->ncsbElement->name,
                ])
                ->values()
                ->all(),
            'sector' => $assessment->sector?->name,
            'sector_maturity_average' => $assessment->sectorMaturityAverage(),
            'sector_constructs' => $assessment->sectorConstructScores
                ->sortBy('sectorConstruct.code')
                ->map(fn (SectorConstructScore $score): array => [
                    'code' => $score->sectorConstruct->code,
                    'name' => $score->sectorConstruct->name,
                    'domain' => $score->sectorConstruct->domain->label(),
                    'level' => $score->maturity_level?->value,
                    'level_label' => $score->maturity_level?->label() ?? 'Unassessed',
                ])->values()->all(),
            'disabled_core_codes' => $assessment->elements->filter->isDisabled()->pluck('element_code')->sort()->values()->all(),
            'generated_by' => $generatedBy->name,
            'generated_at' => $generatedAt->toIso8601String(),
        ];
    }

    /**
     * FR-30 / FR-31: a risk category and plain-language narrative for each of the six domains.
     *
     * @return list<array{domain: string, area: string, score: ?float, risk: string, narrative: string, change: ?string, applicable: bool}>
     */
    public function riskProfile(Assessment $assessment): array
    {
        $applicableDomains = $assessment->sector_id === null ? null : $assessment->loadMissing('elements')
            ->elements->reject->isDisabled()->pluck('domain');

        return array_map(function (Domain $domain) use ($assessment, $applicableDomains): array {
            $score = $assessment->{$domain->scoreColumn()};
            $score = $score === null ? null : (float) $score;
            $risk = RiskCategory::fromDomainScore($score);
            $delta = $assessment->trend?->{$domain->deltaColumn()};
            $applicable = $applicableDomains === null || $applicableDomains->contains($domain);

            return [
                'domain' => $domain->value,
                'area' => $domain->areaName(),
                'score' => $score,
                'risk' => $risk->value,
                'narrative' => $applicable ? $risk->narrative($domain) : 'This area has no applicable core constructs in this cycle.',
                'applicable' => $applicable,
                'change' => match (true) {
                    $delta === null => null,
                    (float) $delta > 0 => 'Improved',
                    (float) $delta < 0 => 'Declined',
                    default => 'No change',
                },
            ];
        }, Domain::cases());
    }

    /** @param list<array{risk: string, applicable: bool}> $domains */
    public function complianceStatus(Assessment $assessment, array $domains): ComplianceStatus
    {
        return ComplianceStatus::determine(
            $assessment->overall_band,
            collect($domains)->where('applicable', true)->map(fn (array $domain): RiskCategory => RiskCategory::from($domain['risk'])),
            $assessment->unanswered_count > 0,
        );
    }

    /**
     * FR-32 / PRD §7.5: rank by gap from Level 3, then GOVERN and RESPOND first, then element code.
     *
     * Level 3 and Unanswered elements are left out. The action is the next level's requirement, verbatim.
     *
     * @return list<array{rank: int, code: string, name: string, area: string, level: int, level_label: string, target_label: string, gap: int, action: string}>
     */
    public function prioritise(Assessment $assessment, int $limit = self::ActionLimit): array
    {
        $assessment->loadMissing('elements.ncsbElement');

        return $assessment->elements
            ->filter(fn (AssessmentElement $element): bool => $element->maturity_level !== null
                && $element->maturity_level !== MaturityLevel::Advanced)
            ->sortBy([
                fn (AssessmentElement $a, AssessmentElement $b): int => $b->maturity_level->gapFromTarget() <=> $a->maturity_level->gapFromTarget(),
                fn (AssessmentElement $a, AssessmentElement $b): int => $b->domain->riskWeight() <=> $a->domain->riskWeight(),
                fn (AssessmentElement $a, AssessmentElement $b): int => strcmp($a->element_code, $b->element_code),
            ])
            ->take($limit)
            ->values()
            ->map(function (AssessmentElement $element, int $index): array {
                $target = MaturityLevel::from($element->maturity_level->value + 1);

                return [
                    'rank' => $index + 1,
                    'code' => $element->element_code,
                    'name' => $element->ncsbElement->name,
                    'area' => $element->domain->areaName(),
                    'level' => $element->maturity_level->value,
                    'level_label' => $element->maturity_level->label(),
                    'target_label' => $target->label(),
                    'gap' => $element->maturity_level->gapFromTarget(),
                    'action' => (string) $element->ncsbElement->requirementFor($target),
                ];
            })
            ->all();
    }
}
