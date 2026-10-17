<?php

namespace App\Http\Controllers;

use App\Enums\AuditCategory;
use App\Enums\Role;
use App\Http\Requests\AuditLogFilterRequest;
use App\Models\AuditLog;
use Illuminate\View\View;

/**
 * The Compliance Officer's audit trail: uploads, deletions, reports, alerts and deadlines (PRD §4.2, DECISIONS D-54).
 */
class AuditTrailController extends Controller
{
    public function index(AuditLogFilterRequest $request): View
    {
        abort_unless($request->user()->hasRole(Role::ComplianceOfficer), 403);

        $filters = $request->validated();

        return view('audit-trail.index', [
            'logs' => AuditLog::query()
                ->with('user')
                ->where('category', AuditCategory::Compliance)
                ->filtered($filters)
                ->paginate(25)
                ->withQueryString(),
            'filters' => $filters,
            'actions' => AuditLog::query()
                ->where('category', AuditCategory::Compliance)
                ->distinct()
                ->orderBy('action')
                ->pluck('action'),
        ]);
    }
}
