<?php

namespace App\Http\Controllers;

use App\Enums\ElementStatus;
use App\Exceptions\TemplateValidationException;
use App\Http\Requests\DeleteAssessmentRequest;
use App\Http\Requests\UploadAssessmentRequest;
use App\Models\Assessment;
use App\Models\AssessmentElement;
use App\Models\AuditLog;
use App\Models\Sector;
use App\Services\AssessmentImportService;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Assessment upload, results and cycle history (FR-10 – FR-17).
 */
class AssessmentController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Assessment::class);

        return view('assessments.index', [
            'assessments' => Assessment::query()
                ->with(['creator', 'sector'])
                ->newestFirst()
                ->get(),
        ]);
    }

    public function showUpload(): View
    {
        Gate::authorize('create', Assessment::class);

        return view('assessments.upload', [
            'sectors' => Sector::query()->with(['constructs' => fn (HasMany $query) => $query->orderBy('code')])->orderBy('name')->get(),
            'recentAssessments' => Assessment::query()
                ->with(['creator', 'sector'])
                ->newestFirst()
                ->limit(5)
                ->get(),
            'recentRejections' => AuditLog::query()
                ->with('user')
                ->where('action', 'assessment.rejected')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit(5)
                ->get(),
        ]);
    }

    public function store(UploadAssessmentRequest $request, AssessmentImportService $importer): RedirectResponse
    {
        try {
            $assessment = $importer->import(
                $request->file('file'),
                $request->string('cycle_label')->trim()->toString(),
                $request->date('assessment_date'),
                $request->user(),
                $request->filled('sector_id') ? $request->integer('sector_id') : null,
                $request->validated('sector_levels', []),
            );
        } catch (TemplateValidationException $exception) {
            return back()
                ->withInput($request->safe()->only(['cycle_label', 'assessment_date', 'sector_id', 'sector_levels']))
                ->withErrors(['file' => $exception->getMessage()]);
        }

        return redirect()
            ->route('assessments.show', $assessment)
            ->with('status', "{$assessment->cycle_label} was uploaded and scored.");
    }

    public function destroy(DeleteAssessmentRequest $request, Assessment $assessment, AssessmentImportService $importer): RedirectResponse
    {
        $importer->undo($assessment, $request->string('reason')->trim()->toString(), $request->user());

        return redirect()
            ->route('assessments.index')
            ->with('status', "{$assessment->cycle_label} was deleted. You can upload a corrected file as a new cycle.");
    }

    public function show(Assessment $assessment): View
    {
        Gate::authorize('view', $assessment);

        $assessment->load(['creator', 'sector', 'sectorConstructScores.sectorConstruct', 'elements.ncsbElement', 'trend.previousAssessment', 'trend.alerts.ncsbElement']);

        $elements = $assessment->elements
            ->sortBy(fn (AssessmentElement $element): int => $element->ncsbElement->sort_order)
            ->values();

        return view('assessments.show', [
            'assessment' => $assessment,
            'elements' => $elements,
            'applicableCount' => $assessment->sector_id === null ? config('ncsb.element_count') : $elements->reject->isDisabled()->count(),
            'unansweredElements' => $elements->filter(fn (AssessmentElement $element): bool => $element->status === ElementStatus::Unanswered),
        ]);
    }
}
