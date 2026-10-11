<?php

namespace App\Http\Controllers;

use App\Enums\Domain;
use App\Models\Assessment;
use App\Models\DegradationAlert;
use App\Services\TrendService;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Compliance trend dashboard (FR-20, FR-21, Fig 5.3).
 */
class TrendController extends Controller
{
    public function index(TrendService $trends): View
    {
        Gate::authorize('viewAny', Assessment::class);

        $assessments = Assessment::query()->with('trend.previousAssessment')->chronological()->get();
        $latest = $assessments->last();
        $latestTrend = $latest?->trend;

        $domainDeltas = collect(Domain::cases())->mapWithKeys(fn (Domain $domain): array => [
            $domain->value => $latestTrend?->{$domain->deltaColumn()},
        ]);

        return view('trends.index', [
            'latest' => $latest,
            'latestTrend' => $latestTrend,
            'improvingCount' => $domainDeltas->filter(fn (?string $delta): bool => $delta !== null && (float) $delta > 0)->count(),
            'decliningCount' => $domainDeltas->filter(fn (?string $delta): bool => $delta !== null && (float) $delta < 0)->count(),
            'chart' => $trends->chartData($assessments),
            'activeAlerts' => DegradationAlert::query()
                ->active()
                ->with('trend.assessment')
                ->orderByDesc('triggered_at')
                ->orderByDesc('id')
                ->get(),
            'acknowledgedAlerts' => DegradationAlert::query()
                ->where('acknowledged', true)
                ->with(['trend.assessment', 'acknowledgedBy'])
                ->orderByDesc('acknowledged_at')
                ->orderByDesc('id')
                ->limit(10)
                ->get(),
        ]);
    }
}
