<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditCategory;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\AuditLogFilterRequest;
use App\Models\AuditLog;
use Illuminate\View\View;

/**
 * Technical audit log for the Admin: sign-ins, denied access, account and settings changes (PRD §4.2, DECISIONS D-54).
 * The compliance trail (uploads, alerts, deadlines) is the Compliance Officer's.
 */
class AuditLogController extends Controller
{
    public function technical(AuditLogFilterRequest $request): View
    {
        abort_unless($request->user()->hasRole(Role::Admin), 403);

        $filters = $request->validated();

        return view('admin.audit-logs.index', [
            'logs' => AuditLog::query()
                ->with('user')
                ->where('category', AuditCategory::Technical)
                ->filtered($filters)
                ->paginate(25)
                ->withQueryString(),
            'filters' => $filters,
            'actions' => AuditLog::query()
                ->where('category', AuditCategory::Technical)
                ->distinct()
                ->orderBy('action')
                ->pluck('action'),
        ]);
    }
}
