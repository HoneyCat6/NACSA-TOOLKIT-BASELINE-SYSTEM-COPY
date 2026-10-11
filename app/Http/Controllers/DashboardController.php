<?php

namespace App\Http\Controllers;

use App\Enums\AuditCategory;
use App\Enums\Role;
use App\Models\Assessment;
use App\Models\AuditLog;
use App\Models\DegradationAlert;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Dispatch to the dashboard for the user's role (FR-70 – FR-73).
     */
    public function index(Request $request): View
    {
        return match ($request->user()->role) {
            Role::Admin => view('dashboard.admin', $this->adminData()),
            Role::ComplianceOfficer => view('dashboard.officer', [
                'latest' => $this->latestAssessment(),
                'activeAlertCount' => DegradationAlert::query()->active()->count(),
            ]),
            Role::ExecutiveReviewer => view('dashboard.executive', [
                'latest' => $this->latestAssessment(),
            ]),
        };
    }

    private function latestAssessment(): ?Assessment
    {
        return Assessment::query()->newestFirst()->first();
    }

    /**
     * FR-70: users by role, system health, recent technical audit entries.
     *
     * @return array<string, mixed>
     */
    private function adminData(): array
    {
        $userCounts = User::query()
            ->selectRaw('role, is_active, count(*) as total')
            ->groupBy('role', 'is_active')
            ->get();

        return [
            'roleCounts' => collect(Role::cases())->mapWithKeys(fn (Role $role): array => [$role->value => [
                'role' => $role,
                'active' => (int) $userCounts->first(fn ($row): bool => $row->role === $role && $row->is_active)?->total,
                'inactive' => (int) $userCounts->first(fn ($row): bool => $row->role === $role && ! $row->is_active)?->total,
            ]]),
            'queuedJobs' => DB::table('jobs')->count(),
            'failedJobs' => DB::table('failed_jobs')->count(),
            'failedLoginsToday' => AuditLog::query()
                ->whereIn('action', ['login.failed', 'login.blocked', 'mfa.locked'])
                ->where('created_at', '>=', now()->subDay())
                ->count(),
            'mailer' => config('mail.default'),
            'recentLogs' => AuditLog::query()
                ->with('user')
                ->where('category', AuditCategory::Technical)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit(8)
                ->get(),
        ];
    }
}
