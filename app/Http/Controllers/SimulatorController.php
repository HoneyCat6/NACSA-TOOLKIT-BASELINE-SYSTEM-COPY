<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProjectSimulationRequest;
use App\Http\Requests\StoreSimulationSessionRequest;
use App\Models\Assessment;
use App\Models\SimulationSession;
use App\Services\SimulationService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Control Effectiveness & Scenario Simulator (FR-60 – FR-65), for officers and executives.
 */
class SimulatorController extends Controller
{
    /**
     * FR-60: opens on the latest cycle; `?baseline=` selects any past cycle.
     */
    public function index(Request $request, SimulationService $simulator): View
    {
        Gate::authorize('viewAny', SimulationSession::class);

        $cycles = Assessment::query()->newestFirst()->get();
        $requested = $request->integer('baseline');

        if ($requested !== 0) {
            $baseline = $cycles->firstWhere('id', $requested);
            abort_if($baseline === null, 404);
        } else {
            $baseline = $cycles->first();
        }

        return $this->simulator($request, $simulator, $cycles, $baseline);
    }

    /**
     * Authoritative server projection (TECH_SPEC §11.1, DECISIONS D-23).
     */
    public function project(ProjectSimulationRequest $request, SimulationService $simulator): JsonResponse
    {
        $baseline = $request->baseline();

        return response()->json([
            'projected' => $simulator->project($baseline, $request->overrides())->toArray(),
            'baseline' => [
                'overall_score' => $baseline->overall_score === null ? null : (float) $baseline->overall_score,
                'overall_band' => $baseline->overall_band->value,
            ],
        ]);
    }

    public function store(StoreSimulationSessionRequest $request, SimulationService $simulator): RedirectResponse
    {
        $session = $simulator->save(
            $request->baseline(),
            $request->overrides(),
            $request->string('scenario_name')->trim()->toString(),
            $request->user(),
        );

        return redirect()
            ->route('simulator.sessions.show', $session)
            ->with('status', "Scenario \"{$session->scenario_name}\" was saved.");
    }

    /**
     * FR-64: reload a saved scenario on its own baseline cycle.
     */
    public function show(Request $request, SimulationSession $session, SimulationService $simulator): View
    {
        Gate::authorize('view', $session);

        return $this->simulator($request, $simulator, Assessment::query()->newestFirst()->get(), $session->baseline, $session);
    }

    public function destroy(Request $request, SimulationSession $session, SimulationService $simulator): RedirectResponse
    {
        Gate::authorize('delete', $session);

        $simulator->delete($session, $request->user());

        return redirect()
            ->route('simulator.index')
            ->with('status', "Scenario \"{$session->scenario_name}\" was deleted.");
    }

    /**
     * @param  Collection<int, Assessment>  $cycles
     */
    private function simulator(Request $request, SimulationService $simulator, Collection $cycles, ?Assessment $baseline, ?SimulationSession $session = null): View
    {
        return view('simulator.index', [
            'cycles' => $cycles,
            'baseline' => $baseline,
            'session' => $session,
            'state' => $baseline ? $simulator->baselineState($baseline) : null,
            'rules' => $simulator->projectionRules($baseline),
            // After a failed save the sliders come back as they were; a reloaded scenario starts from its saved levels.
            'initialOverrides' => (object) $request->old('overrides', $session?->modified_responses ?? []),
            'sessions' => SimulationSession::query()
                ->whereBelongsTo($request->user(), 'creator')
                ->with('baseline')
                ->latest()
                ->latest('id')
                ->get(),
        ]);
    }
}
