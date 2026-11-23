<?php

namespace App\Http\Controllers;

use App\Enums\Domain;
use App\Models\Assessment;
use App\Models\DegradationAlert;
use App\Models\Sector;
use App\Services\TrendService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Compliance trend dashboard (FR-20, FR-21, Fig 5.3), with per-element history (DECISIONS D-36).
 */
class TrendController extends Controller
{
    public function index(Request $request, TrendService $trends): View
    {
        Gate::authorize('viewTrends', Assessment::class);

        $sectors = Sector::query()->orderBy('name')->get();
        $sectorId = $request->filled('sector') ? $request->integer('sector') : Assessment::query()->newestFirst()->value('sector_id');
        $sector = $sectorId ? $sectors->find($sectorId) : null;
        abort_if($sectorId && $sector === null, 404);

        $assessments = Assessment::query()->where('sector_id', $sector?->id)->with('trend.previousAssessment')->chronological()->get();
        $latest = $assessments->last();
        $latestTrend = $latest?->trend;
        $elementHistory = $trends->elementHistory($assessments);

        $domainDeltas = collect(Domain::cases())->mapWithKeys(fn (Domain $domain): array => [
            $domain->value => $latestTrend?->{$domain->deltaColumn()},
        ]);

        return view('trends.index', [
            'sectors' => $sectors,
            'sector' => $sector,
            'latest' => $latest,
            'latestTrend' => $latestTrend,
            'improvingCount' => $domainDeltas->filter(fn (?string $delta): bool => $delta !== null && (float) $delta > 0)->count(),
            'decliningCount' => $domainDeltas->filter(fn (?string $delta): bool => $delta !== null && (float) $delta < 0)->count(),
            'decliningElementCount' => collect($elementHistory)->filter(fn (array $element): bool => ($element['latest_delta'] ?? 0) < 0)->count(),
            'chart' => $trends->chartData($assessments),
            'elementHistory' => $elementHistory,
            'activeAlerts' => DegradationAlert::query()
                ->active()
                ->with(['trend.assessment', 'ncsbElement'])
                ->orderByDesc('triggered_at')
                ->orderByRaw('ncsb_element_id is not null')
                ->orderBy('id')
                ->get(),
            'acknowledgedAlerts' => DegradationAlert::query()
                ->where('acknowledged', true)
                ->with(['trend.assessment', 'acknowledgedBy', 'ncsbElement'])
                ->orderByDesc('acknowledged_at')
                ->orderByDesc('id')
                ->limit(10)
                ->get(),
        ]);
    }
}
