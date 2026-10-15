<?php

namespace App\Http\Controllers;

use App\Enums\AuditCategory;
use App\Http\Requests\GenerateReportRequest;
use App\Models\Assessment;
use App\Models\ExecutiveReport;
use App\Services\AuditService;
use App\Services\ReportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Executive summary reports (FR-33, FR-34): generate per cycle, list versions, download.
 */
class ReportController extends Controller
{
    /**
     * Officers see every cycle with a generate button; executives see only cycles that have a report (D-37).
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', ExecutiveReport::class);

        $canGenerate = $request->user()->can('create', ExecutiveReport::class);

        return view('reports.index', [
            'canGenerate' => $canGenerate,
            'assessments' => Assessment::query()
                ->with(['reports' => fn (HasMany $query) => $query->with('generator')->orderBy('generated_at')->orderBy('id')])
                ->unless($canGenerate, fn (Builder $query) => $query->has('reports'))
                ->newestFirst()
                ->get(),
        ]);
    }

    public function generate(GenerateReportRequest $request, Assessment $assessment, ReportService $reports): RedirectResponse
    {
        $report = $reports->generate($assessment, $request->user());

        return redirect()
            ->to(route('reports.index').'#cycle-'.$assessment->id)
            ->with('status', "Version {$report->payload['version']} of the {$assessment->cycle_label} executive report is ready to download.");
    }

    /**
     * Serves the stored PDF as generated — reports never regenerate silently (FR-34).
     */
    public function download(ExecutiveReport $report, AuditService $audit): StreamedResponse
    {
        Gate::authorize('download', $report);

        $disk = Storage::disk(ReportService::Disk);
        abort_unless($disk->exists($report->file_path), 404);

        $audit->log('report.downloaded', AuditCategory::Compliance, $report, [
            'cycle_label' => $report->assessment->cycle_label,
        ]);

        return $disk->download($report->file_path, $report->downloadFilename(), ['Content-Type' => 'application/pdf']);
    }
}
