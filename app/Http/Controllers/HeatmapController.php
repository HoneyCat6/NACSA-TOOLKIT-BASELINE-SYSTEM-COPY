<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Services\HeatmapService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Control Effectiveness Heatmap (FR-50 – FR-54), visible to officers and executives (DECISIONS D-37 keeps
 * executives out of cycle results, but the heatmap and trends are explicitly theirs).
 */
class HeatmapController extends Controller
{
    public function index(Request $request, HeatmapService $heatmap): View
    {
        Gate::authorize('viewHeatmap', Assessment::class);

        $cycles = $heatmap->cycles();
        $cycle = $this->resolveCycle($request, $cycles);

        return view('heatmap.index', [
            'cycles' => $cycles,
            'cycle' => $cycle,
            'grid' => $cycle ? $heatmap->grid($cycle) : [],
        ]);
    }

    public function detail(Request $request, HeatmapService $heatmap, string $code): JsonResponse
    {
        Gate::authorize('viewHeatmap', Assessment::class);

        $cycle = $this->resolveCycle($request, $heatmap->cycles());

        abort_if($cycle === null, 404);

        return response()->json($heatmap->detail($cycle, $code));
    }

    /**
     * The requested cycle (`?cycle=`), falling back to the latest; null when none exist.
     *
     * @param  Collection<int, Assessment>  $cycles
     */
    private function resolveCycle(Request $request, $cycles): ?Assessment
    {
        $requested = $request->integer('cycle');

        if ($requested !== 0) {
            $cycle = $cycles->firstWhere('id', $requested);
            abort_if($cycle === null, 404);

            return $cycle;
        }

        return $cycles->first();
    }
}
