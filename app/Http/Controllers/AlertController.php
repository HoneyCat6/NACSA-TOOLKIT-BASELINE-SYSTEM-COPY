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

        $alert->update([
            'acknowledged' => true,
            'acknowledged_by' => $request->user()->id,
            'acknowledged_at' => now(),
        ]);

        $audit->log('alert.acknowledge', auditable: $alert, metadata: ['domain' => $alert->domain->value]);

        return back()->with('status', "The {$alert->domain->label()} alert was acknowledged.");
    }
}
