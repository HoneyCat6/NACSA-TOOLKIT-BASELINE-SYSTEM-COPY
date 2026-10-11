<?php

namespace App\Http\Controllers;

use App\Models\DegradationAlert;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Degradation alert acknowledgement (FR-20).
 */
class AlertController extends Controller
{
    public function acknowledge(Request $request, DegradationAlert $alert, AuditService $audit): RedirectResponse
    {
        Gate::authorize('acknowledge', $alert);

        if ($alert->acknowledged) {
            return back()->with('status', 'This alert was already acknowledged.');
        }

        $this->markAcknowledged($request, $alert, $audit);

        return back()->with('status', "The {$alert->subjectLabel()} alert was acknowledged.");
    }

    /**
     * One element decline can raise many alerts (DECISIONS D-36), so officers can clear them together.
     * Each alert still gets its own audit row.
     */
    public function acknowledgeAll(Request $request, AuditService $audit): RedirectResponse
    {
        Gate::authorize('acknowledgeAll', DegradationAlert::class);

        $alerts = DegradationAlert::query()->active()->with('ncsbElement')->get();

        $alerts->each(fn (DegradationAlert $alert) => $this->markAcknowledged($request, $alert, $audit));

        return back()->with('status', $alerts->count().' '.str('alert')->plural($alerts->count()).' acknowledged.');
    }

    private function markAcknowledged(Request $request, DegradationAlert $alert, AuditService $audit): void
    {
        $alert->update([
            'acknowledged' => true,
            'acknowledged_by' => $request->user()->id,
            'acknowledged_at' => now(),
        ]);

        $audit->log('alert.acknowledge', auditable: $alert, metadata: array_filter([
            'domain' => $alert->domain->value,
            'element_code' => $alert->ncsbElement?->code,
        ]));
    }
}
