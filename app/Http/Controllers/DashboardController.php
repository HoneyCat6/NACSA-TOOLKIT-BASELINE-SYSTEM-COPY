<?php

namespace App\Http\Controllers;

use App\Enums\AuditCategory;
use App\Enums\Domain;
use App\Enums\NotificationType;
use App\Enums\Role;
use App\Models\Assessment;
use App\Models\AuditLog;
use App\Models\ComplianceDeadline;
use App\Models\DeadlineNotification;
use App\Models\DegradationAlert;
use App\Models\ExecutiveReport;
use App\Models\User;
use App\Services\ForecastService;
use App\Services\ReportService;
use App\Services\SystemHealthService;
use App\Services\TrendService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Window for the officer's notification history summary (FR-71).
     */
    public const NotificationSummaryDays = 30;

    /**
     * How many deadlines, alerts and notifications the officer's panels list before linking to the full page.
     */
    public const PanelLimit = 5;

    /**
     * Dispatch to the dashboard for the user's role (FR-70 – FR-73).
     */
    public function index(Request $request, SystemHealthService $health, TrendService $trends, ReportService $reports, ForecastService $forecasts): View
    {
        return match ($request->user()->role) {
            Role::Admin => view('dashboard.admin', $this->adminData($health)),
            Role::ComplianceOfficer => view('dashboard.officer', $this->officerData($trends)),
            Role::ExecutiveReviewer => view('dashboard.executive', $this->executiveData($reports, $forecasts)),
        };
    }

    /**
     * FR-70: users by role, system health, recent technical audit entries.
     *
     * @return array<string, mixed>
     */
    private function adminData(SystemHealthService $health): array
    {
        $userCounts = User::query()
            ->selectRaw('role, is_active, count(*) as total')
            ->groupBy('role', 'is_active')
            ->get();

        $roleCounts = collect(Role::cases())->mapWithKeys(fn (Role $role): array => [$role->value => [
            'role' => $role,
            'active' => (int) $userCounts->first(fn ($row): bool => $row->role === $role && $row->is_active)?->total,
            'inactive' => (int) $userCounts->first(fn ($row): bool => $row->role === $role && ! $row->is_active)?->total,
        ]]);

        return [
            'roleCounts' => $roleCounts,
            'hasActiveOfficer' => $roleCounts[Role::ComplianceOfficer->value]['active'] > 0,
            'queuedJobs' => $health->queuedJobCount(),
            'failedJobs' => $health->failedJobCount(),
            'lastSchedulerRun' => $health->lastSchedulerRun(),
            'schedulerRunning' => $health->isSchedulerRunning(),
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

    /**
     * FR-71: latest result, deadline countdowns, notification summary, active alerts and per-domain trends.
     *
     * @return array<string, mixed>
     */
    private function officerData(TrendService $trends): array
    {
        $latestSector = Assessment::query()->newestFirst()->value('sector_id');
        $assessments = Assessment::query()->where('sector_id', $latestSector)->with(['trend', 'sector'])->chronological()->get();
        $openDeadlines = ComplianceDeadline::query()->open()->with('officer')->orderBy('due_date')->orderBy('id')->get();

        return [
            'latest' => $assessments->last(),
            'activeAlertCount' => DegradationAlert::query()->active()->count(),
            'activeAlerts' => DegradationAlert::query()
                ->active()
                ->with(['trend.assessment', 'ncsbElement'])
                ->orderByDesc('triggered_at')
                ->orderByRaw('ncsb_element_id is not null')
                ->orderBy('id')
                ->limit(self::PanelLimit)
                ->get(),
            'overdueDeadlineCount' => $openDeadlines->filter(fn (ComplianceDeadline $deadline): bool => $deadline->daysRemaining() < 0)->count(),
            'openDeadlineCount' => $openDeadlines->count(),
            'activeDeadlines' => $openDeadlines->take(self::PanelLimit),
            'failedNotificationCount' => DeadlineNotification::query()->failed()->count(),
            'notificationSummary' => $this->notificationSummary(),
            'recentNotifications' => DeadlineNotification::query()
                ->with(['deadline', 'recipient'])
                ->orderByDesc('dispatched_at')
                ->orderByDesc('id')
                ->limit(self::PanelLimit)
                ->get(),
            'cycleLabels' => $assessments->pluck('cycle_label')->all(),
            'domainTrends' => $this->domainTrends($trends, $assessments),
        ];
    }

    /**
     * FR-72: posture indicator, domain risk summary, latest report, shortcuts. FR-73: who to ask for the first upload.
     *
     * @return array<string, mixed>
     */
    private function executiveData(ReportService $reports, ForecastService $forecasts): array
    {
        $latestSector = Assessment::query()->newestFirst()->value('sector_id');
        $assessments = Assessment::query()->where('sector_id', $latestSector)->with(['trend', 'sector'])->newestFirst()->limit(ForecastService::AssessmentCount)->get()->reverse()->values();
        $latest = $assessments->last();
        $riskProfile = $latest === null ? [] : $reports->riskProfile($latest);

        return [
            'latest' => $latest,
            'riskProfile' => $riskProfile,
            'forecast' => $forecasts->outlook($assessments),
            'complianceStatus' => $latest === null ? null : $reports->complianceStatus($latest, $riskProfile),
            'latestReport' => ExecutiveReport::query()
                ->with('assessment')
                ->orderByDesc('generated_at')
                ->orderByDesc('id')
                ->first(),
            'officerEmails' => $latest === null
                ? User::query()->where('role', Role::ComplianceOfficer)->where('is_active', true)->orderBy('name')->pluck('email')->all()
                : [],
        ];
    }

    /**
     * Reminders, escalations and their delivery state over the last NotificationSummaryDays days (FR-45, FR-46).
     *
     * @return array{reminders: int, escalations: int, delivered: int, pending: int, failed: int}
     */
    private function notificationSummary(): array
    {
        $totals = DeadlineNotification::query()
            ->toBase()
            ->where('dispatched_at', '>=', now()->subDays(self::NotificationSummaryDays))
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(type = ?) as reminders', [NotificationType::Reminder->value])
            ->selectRaw('sum(type = ?) as escalations', [NotificationType::Escalation->value])
            ->selectRaw('sum(delivered = 1) as delivered')
            ->selectRaw('sum(delivered = 0 and failure_reason is not null) as failed')
            ->first();

        $delivered = (int) $totals->delivered;
        $failed = (int) $totals->failed;

        return [
            'reminders' => (int) $totals->reminders,
            'escalations' => (int) $totals->escalations,
            'delivered' => $delivered,
            'pending' => (int) $totals->total - $delivered - $failed,
            'failed' => $failed,
        ];
    }

    /**
     * One small trend chart per domain: its scores across cycles, latest score and change vs the previous cycle.
     *
     * @param  Collection<int, Assessment>  $assessments  in chronological order, with `trend` loaded
     * @return list<array{domain: Domain, label: string, chart_id: string, data: list<?float>, declining: bool, latest: ?float, delta: ?string}>
     */
    private function domainTrends(TrendService $trends, Collection $assessments): array
    {
        $latestTrend = $assessments->last()?->trend;
        $chart = $trends->chartData($assessments);

        return array_map(function (array $dataset) use ($latestTrend): array {
            $domain = Domain::from($dataset['label']);

            return [
                'domain' => $domain,
                'label' => $domain->label(),
                'chart_id' => 'domain-trend-'.$domain->elementCodePrefix(),
                'data' => $dataset['data'],
                'declining' => $dataset['declining'],
                'latest' => $dataset['data'] === [] ? null : end($dataset['data']),
                'delta' => $latestTrend?->{$domain->deltaColumn()},
            ];
        }, $chart['datasets']);
    }
}
