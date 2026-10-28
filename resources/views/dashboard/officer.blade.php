@extends('layouts.app')

@section('title', 'Compliance dashboard')

@section('content')
    <h1 class="h3 mb-3">Compliance dashboard</h1>

    @if ($activeAlertCount > 0)
        <div class="alert alert-danger d-flex flex-wrap justify-content-between align-items-center gap-2" role="alert">
            <span><strong>{{ $activeAlertCount }}</strong> active degradation {{ Str::plural('alert', $activeAlertCount) }} need acknowledgement.</span>
            <a href="{{ route('trends.index') }}" class="btn btn-sm btn-danger">Review alerts</a>
        </div>
    @endif

    @if ($overdueDeadlineCount > 0)
        <div class="alert alert-danger d-flex flex-wrap justify-content-between align-items-center gap-2" role="alert">
            <span><strong>{{ $overdueDeadlineCount }}</strong> compliance {{ Str::plural('deadline', $overdueDeadlineCount) }} {{ $overdueDeadlineCount === 1 ? 'is' : 'are' }} overdue.</span>
            <a href="{{ route('deadlines.index') }}" class="btn btn-sm btn-danger">View deadlines</a>
        </div>
    @endif

    @if ($failedNotificationCount > 0)
        <div class="alert alert-warning d-flex flex-wrap justify-content-between align-items-center gap-2" role="alert">
            <span><strong>{{ $failedNotificationCount }}</strong> deadline {{ Str::plural('email', $failedNotificationCount) }} could not be delivered after retrying.</span>
            <a href="{{ route('notifications.index', ['delivery' => 'failed']) }}" class="btn btn-sm btn-warning">View failures</a>
        </div>
    @endif

    @if ($latest)
        @include('dashboard.partials.latest-assessment')

        <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mt-4 mb-2">
            <div>
                <h2 class="h5 mb-0">Domain trends</h2>
                <div class="small text-muted">Average element maturity within each domain (0–3) across {{ count($cycleLabels) }} {{ Str::plural('cycle', count($cycleLabels)) }}. Domains that fell in the latest cycle are drawn in red with ▼.</div>
            </div>
            <a href="{{ route('trends.index') }}" class="btn btn-sm btn-outline-secondary">Full trends and data table</a>
        </div>
        <div class="row g-3 mb-4">
            @foreach ($domainTrends as $trend)
                <div class="col-sm-6 col-xl-4 col-xxl-2">
                    <div @class(['card shadow-sm h-100', 'border-danger' => $trend['declining']])>
                        <div class="card-body pb-2">
                            <div class="d-flex justify-content-between align-items-baseline gap-2">
                                <h3 class="h6 mb-0">{{ $trend['label'] }}</h3>
                                <span class="fs-5 fw-semibold">{{ $trend['latest'] === null ? '—' : number_format($trend['latest'], 2) }}</span>
                            </div>
                            <div class="small">
                                @if ($trend['latest'] === null)
                                    <span class="text-muted">Unanswered in the latest cycle</span>
                                @elseif ($trend['delta'] === null)
                                    <span class="text-muted">{{ count($cycleLabels) > 1 ? 'No comparable previous score' : 'Needs a second cycle to compare' }}</span>
                                @else
                                    @include('assessments.partials.delta', ['delta' => $trend['delta']])
                                @endif
                            </div>
                            <div style="position: relative; height: 110px;" class="mt-2">
                                <canvas id="{{ $trend['chart_id'] }}" role="img"
                                        aria-label="{{ $trend['label'] }} domain score across assessment cycles"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="card shadow-sm mb-4">
            <div class="card-body text-center py-5">
                <p class="lead mb-1">No assessments yet</p>
                <p class="text-muted mb-3">Upload your first completed NCSB v1.4 toolkit to see maturity results and trends.</p>
                <a href="{{ route('assessments.upload') }}" class="btn btn-primary">Upload assessment</a>
            </div>
        </div>
    @endif

    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h2 class="h5 mb-0">Upcoming deadlines</h2>
                    <a href="{{ route('deadlines.index') }}" class="btn btn-sm btn-outline-secondary">
                        {{ $openDeadlineCount > $activeDeadlines->count() ? "All {$openDeadlineCount} open" : 'Deadlines' }}
                    </a>
                </div>
                @if ($activeDeadlines->isEmpty())
                    <div class="card-body text-center py-4">
                        <p class="text-muted mb-3">No open deadlines. Add the date your next self-assessment is due so reminders go out on time.</p>
                        <a href="{{ route('deadlines.create') }}" class="btn btn-sm btn-primary">Add deadline</a>
                    </div>
                @else
                    <ul class="list-group list-group-flush">
                        @foreach ($activeDeadlines as $deadline)
                            @php($status = $deadline->deriveStatus())
                            @php($daysRemaining = $deadline->daysRemaining())
                            <li class="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-2">
                                <div>
                                    <div class="fw-semibold">{{ $deadline->title }}</div>
                                    <div class="small text-muted">Due {{ $deadline->due_date->format('l, j M Y') }} · {{ $deadline->officer->name }}</div>
                                </div>
                                <div class="text-end">
                                    <span class="badge {{ $status->badgeClass() }}">{{ $status->label() }}</span>
                                    <div @class(['fw-semibold', 'text-danger' => $daysRemaining < 0])>
                                        @include('deadlines.partials.days-remaining')
                                    </div>
                                    @if ($daysRemaining >= 0)
                                        <div class="small text-muted font-monospace" style="font-variant-numeric: tabular-nums;"
                                             x-data="deadlineCountdown({{ Js::from($deadline->due_date->copy()->endOfDay()->toIso8601String()) }})"
                                             x-show="remaining" x-cloak
                                             title="Time left until the end of the due date">
                                            <span x-text="remaining"></span>
                                        </div>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h2 class="h5 mb-0">Active degradation alerts</h2>
                    <a href="{{ route('trends.index') }}" class="btn btn-sm btn-outline-secondary">
                        {{ $activeAlertCount > $activeAlerts->count() ? "All {$activeAlertCount} alerts" : 'Trends' }}
                    </a>
                </div>
                @if ($activeAlerts->isEmpty())
                    <div class="card-body">
                        <p class="text-muted mb-0">No active alerts. An alert is raised whenever a domain score or an element's level falls between cycles.</p>
                    </div>
                @else
                    <ul class="list-group list-group-flush">
                        @foreach ($activeAlerts as $alert)
                            <li class="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-2">
                                <div class="small">
                                    @include('trends.partials.alert-summary', ['alert' => $alert])
                                    <div class="text-muted">Raised {{ $alert->triggered_at->format('j M Y, H:i') }}</div>
                                </div>
                                @can('acknowledge', $alert)
                                    <form method="POST" action="{{ route('alerts.acknowledge', $alert) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Acknowledge</button>
                                    </form>
                                @endcan
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h2 class="h5 mb-0">Notification history</h2>
                <div class="small text-muted">Deadline emails dispatched in the last {{ App\Http\Controllers\DashboardController::NotificationSummaryDays }} days</div>
            </div>
            <a href="{{ route('notifications.index') }}" class="btn btn-sm btn-outline-secondary">Full history</a>
        </div>
        <div class="card-body">
            <div class="row g-3 text-center">
                <div class="col-6 col-md">
                    <div class="fs-4 fw-semibold">{{ $notificationSummary['reminders'] }}</div>
                    <div class="small text-muted">{{ Str::plural('Reminder', $notificationSummary['reminders']) }}</div>
                </div>
                <div class="col-6 col-md">
                    <div @class(['fs-4 fw-semibold', 'text-danger' => $notificationSummary['escalations'] > 0])>{{ $notificationSummary['escalations'] }}</div>
                    <div class="small text-muted">{{ Str::plural('Escalation', $notificationSummary['escalations']) }}</div>
                </div>
                <div class="col-4 col-md">
                    <div class="fs-4 fw-semibold">{{ $notificationSummary['delivered'] }}</div>
                    <div class="small text-muted">Delivered</div>
                </div>
                <div class="col-4 col-md">
                    <div class="fs-4 fw-semibold">{{ $notificationSummary['pending'] }}</div>
                    <div class="small text-muted">Pending</div>
                </div>
                <div class="col-4 col-md">
                    <div @class(['fs-4 fw-semibold', 'text-danger' => $notificationSummary['failed'] > 0])>{{ $notificationSummary['failed'] }}</div>
                    <div class="small text-muted">Failed</div>
                </div>
            </div>
        </div>
        @if ($recentNotifications->isEmpty())
            <div class="card-footer bg-white text-muted small">No reminders or escalations sent yet.</div>
        @else
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Dispatched</th>
                            <th scope="col">Type</th>
                            <th scope="col">Deadline</th>
                            <th scope="col">Recipient</th>
                            <th scope="col">Delivery</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recentNotifications as $notification)
                            <tr>
                                <td class="text-nowrap small">{{ $notification->dispatched_at->format('j M Y, H:i') }}</td>
                                <td>
                                    @if ($notification->type === App\Enums\NotificationType::Escalation)
                                        <span class="badge text-bg-danger">Escalation</span>
                                    @else
                                        <span class="badge text-bg-primary">Reminder · {{ $notification->lead_day }}d</span>
                                    @endif
                                </td>
                                <td class="small">{{ $notification->deadline->title }}</td>
                                <td class="small">{{ $notification->recipient->name }}</td>
                                <td class="small">
                                    <span @class([
                                        'badge',
                                        'text-bg-success' => $notification->delivered,
                                        'text-bg-danger' => $notification->hasFailed(),
                                        'text-bg-secondary' => ! $notification->delivered && ! $notification->hasFailed(),
                                    ])>{{ $notification->deliveryLabel() }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection

@push('scripts')
    <script>
        /**
         * Live countdown to the end of a deadline's due date (FR-71). Empty once it has passed;
         * the server-rendered "N days left" text stays as the status for screen readers and no-JS.
         */
        function deadlineCountdown(dueAt) {
            const pad = (value) => String(value).padStart(2, '0');

            return {
                remaining: '',
                timer: null,
                init() {
                    this.tick();
                    this.timer = setInterval(() => this.tick(), 1000);
                },
                destroy() {
                    clearInterval(this.timer);
                },
                tick() {
                    const seconds = Math.floor((new Date(dueAt) - Date.now()) / 1000);

                    if (seconds <= 0) {
                        this.remaining = '';
                        clearInterval(this.timer);

                        return;
                    }

                    const days = Math.floor(seconds / 86400);
                    const hours = Math.floor((seconds % 86400) / 3600);
                    const minutes = Math.floor((seconds % 3600) / 60);

                    this.remaining = `${days}d ${pad(hours)}h ${pad(minutes)}m ${pad(seconds % 60)}s`;
                },
            };
        }
    </script>
@endpush

@if ($latest)
    @push('scripts')
        <script type="application/json" id="domain-trend-data">@json(['labels' => $cycleLabels, 'domains' => $domainTrends])</script>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const data = JSON.parse(document.getElementById('domain-trend-data').textContent);
                // Same per-domain colours as the trends page, in domain order; red is reserved for "declining" (FR-21).
                const seriesColours = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300'];
                const alertColour = '#dc3545';
                const textColour = '#52514e';
                const gridColour = 'rgba(0, 0, 0, 0.08)';
                const lastIndex = data.labels.length - 1;

                data.domains.forEach((domain, index) => {
                    const colour = domain.declining ? alertColour : seriesColours[index];

                    new Chart(document.getElementById(domain.chart_id), {
                        type: 'line',
                        data: {
                            labels: data.labels,
                            datasets: [{
                                label: domain.label,
                                data: domain.data,
                                borderColor: colour,
                                backgroundColor: colour,
                                borderWidth: 2,
                                borderDash: domain.declining ? [6, 4] : [],
                                pointStyle: domain.declining ? 'triangle' : 'circle',
                                pointRadius: (context) => context.dataIndex === lastIndex ? (domain.declining ? 6 : 4) : 2,
                                pointHoverRadius: 6,
                                pointHitRadius: 12,
                                spanGaps: true,
                                tension: 0,
                            }],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: { mode: 'index', intersect: false },
                            scales: {
                                x: { display: false },
                                y: { min: 0, max: 3, ticks: { stepSize: 1, color: textColour, font: { size: 10 } }, grid: { color: gridColour } },
                            },
                            plugins: {
                                legend: { display: false },
                                tooltip: { callbacks: { label: (context) => `${domain.label}: ${context.parsed.y === null ? 'Unanswered' : context.parsed.y.toFixed(2)}` } },
                            },
                        },
                    });
                });
            });
        </script>
    @endpush
@endif
