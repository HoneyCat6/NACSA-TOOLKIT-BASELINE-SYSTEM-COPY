<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditCategory;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Technical audit log for the Admin: sign-ins, denied access, account and settings changes (PRD §4.2, DECISIONS D-54).
 * The compliance trail (uploads, alerts, deadlines) is the Compliance Officer's.
 */
class AuditLogController extends Controller
{
    public function technical(Request $request): View
    {
        abort_unless($request->user()->hasRole(Role::Admin), 403);

        $filters = $request->validate([
            'action' => ['nullable', 'string', 'max:100'],
            'user' => ['nullable', 'string', 'max:255'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $logs = AuditLog::query()
            ->with('user')
            ->where('category', AuditCategory::Technical)
            ->when($filters['action'] ?? null, fn ($query, string $action) => $query->where('action', $action))
            ->when($filters['user'] ?? null, fn ($query, string $search) => $query->whereHas(
                'user',
                fn ($users) => $users->where(fn ($match) => $match->where('email', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%")),
            ))
            ->when($filters['from'] ?? null, fn ($query, string $from) => $query->where('created_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($filters['to'] ?? null, fn ($query, string $to) => $query->where('created_at', '<=', Carbon::parse($to)->endOfDay()))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.audit-logs.index', [
            'logs' => $logs,
            'filters' => $filters,
            'actions' => AuditLog::query()
                ->where('category', AuditCategory::Technical)
                ->distinct()
                ->orderBy('action')
                ->pluck('action'),
        ]);
    }
}
