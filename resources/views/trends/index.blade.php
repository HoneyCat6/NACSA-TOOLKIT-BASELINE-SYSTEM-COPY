@extends('layouts.app')

@section('title', 'Compliance trends')

@section('content')
    <h1 class="h3 mb-3">Compliance trends</h1>

    @if ($latest === null)
        <div class="card shadow-sm">
            <div class="card-body text-center py-5">
                <p class="lead mb-1">No assessment cycles yet</p>
                <p class="text-muted mb-3">Trends appear once cycles are uploaded. Two cycles are needed to compare.</p>
                @can('create', App\Models\Assessment::class)
                    <a href="{{ route('assessments.upload') }}" class="btn btn-primary">Upload the first assessment</a>
                @endcan
            </div>
        </div>
    @else
        <div class="row g-3 mb-4">
            <div class="col-md-6 col-xl-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="small text-muted mb-1">Overall maturity · {{ $latest->cycle_label }}</div>
                        @include('partials.severity-badge', [
                            'severity' => $latest->overall_band->severity(),
                            'label' => $latest->overall_band->value,
                            'class' => 'fs-5',
                        ])
                        <span class="ms-2">score <strong>{{ $latest->overall_score }}</strong></span>
                        <div class="small text-muted mt-1">Share of the 33 elements with at least one control implemented (0–1)</div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="small text-muted mb-1">Improving domains</div>
                        <div class="fs-4 fw-semibold">{{ $latestTrend ? $improvingCount : '—' }}</div>
                        <div class="small text-muted">{{ $latestTrend ? 'of 6, vs '.$latestTrend->previousAssessment?->cycle_label : 'Needs a second cycle' }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card shadow-sm h-100 @if ($decliningCount > 0) border-danger @endif">
                    <div class="card-body">
                        <div class="small text-muted mb-1">Domains at risk</div>
                        <div class="fs-4 fw-semibold @if ($decliningCount > 0) text-danger @endif">
                            {{ $latestTrend ? $decliningCount : '—' }}
                            @if ($decliningCount > 0)<span class="fs-6">▼ declined</span>@endif
                        </div>
                        <div class="small text-muted">{{ $latestTrend ? 'Domain score fell in the latest cycle' : 'Needs a second cycle' }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card shadow-sm h-100 @if ($decliningElementCount > 0) border-danger @endif">
                    <div class="card-body">
                        <div class="small text-muted mb-1">Elements declined</div>
                        <div class="fs-4 fw-semibold @if ($decliningElementCount > 0) text-danger @endif">
                            {{ $latestTrend ? $decliningElementCount : '—' }}
                            @if ($decliningElementCount > 0)<span class="fs-6">▼ of 33</span>@endif
                        </div>
                        <div class="small text-muted">{{ $latestTrend ? 'Element maturity level fell in the latest cycle' : 'Needs a second cycle' }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-xl-8">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white">
                        <h2 class="h5 mb-0">Domain scores across cycles</h2>
                        <div class="small text-muted">Average element maturity within each domain (0–3). Declining domains are drawn dashed in red with ▲ markers.</div>
                    </div>
                    <div class="card-body">
                        <div style="position: relative; height: 340px;">
                            <canvas id="domain-trend-chart" role="img" aria-label="Line chart of the six domain scores across assessment cycles"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white">
                        <h2 class="h5 mb-0">Overall score across cycles</h2>
                        <div class="small text-muted">Share of the 33 elements with at least one control implemented (0–1)</div>
                    </div>
                    <div class="card-body">
                        <div style="position: relative; height: 340px;">
                            <canvas id="overall-trend-chart" role="img" aria-label="Line chart of the overall score across assessment cycles"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <details class="card shadow-sm mb-4">
            <summary class="card-header bg-white">Data table</summary>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Cycle</th>
                            @foreach ($chart['datasets'] as $dataset)
                                <th scope="col" class="text-end">{{ $dataset['label'] }}{{ $dataset['declining'] ? ' ▼' : '' }}</th>
                            @endforeach
                            <th scope="col" class="text-end">Overall (0–1)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($chart['labels'] as $index => $label)
                            <tr>
                                <th scope="row">{{ $label }}</th>
                                @foreach ($chart['datasets'] as $dataset)
                                    <td class="text-end">{{ $dataset['data'][$index] === null ? '—' : number_format($dataset['data'][$index], 2) }}</td>
                                @endforeach
                                <td class="text-end">{{ number_format($chart['overall'][$index], 3) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white">
                <h2 class="h5 mb-0">Element maturity across cycles</h2>
                <div class="small text-muted">Level 0–3 of each of the 33 NCSB v1.4 elements in every cycle. Elements whose level fell in the latest cycle are marked ▼.</div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Code</th>
                            <th scope="col">Element</th>
                            @foreach ($chart['labels'] as $label)
                                <th scope="col" class="text-center text-nowrap">{{ $label }}</th>
                            @endforeach
                            <th scope="col" class="text-nowrap">Latest change</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($elementHistory as $element)
                            <tr @class(['table-danger' => ($element['latest_delta'] ?? 0) < 0])>
                                <td class="fw-semibold text-nowrap">{{ $element['code'] }}</td>
                                <td class="small">{{ $element['name'] }}</td>
                                @foreach ($element['levels'] as $level)
                                    <td class="text-center">
                                        @if ($level === null)
                                            <span class="text-muted small">—</span>
                                        @else
                                            @php($maturity = App\Enums\MaturityLevel::from($level))
                                            @include('partials.severity-badge', ['severity' => $maturity->severity(), 'label' => $level])
                                        @endif
                                    </td>
                                @endforeach
                                <td class="text-nowrap small">
                                    @if ($element['latest_delta'] === null)
                                        <span class="text-muted">—</span>
                                    @else
                                        @include('assessments.partials.delta', ['delta' => $element['latest_delta']])
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                <h2 class="h5 mb-0">Active degradation alerts</h2>
                @if ($activeAlerts->count() > 1)
                    @can('acknowledgeAll', App\Models\DegradationAlert::class)
                        <form method="POST" action="{{ route('alerts.acknowledge-all') }}"
                              @submit="if (! confirm({{ Js::from('Acknowledge all '.$activeAlerts->count().' active alerts?') }})) $event.preventDefault()"
                              x-data>
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-danger">Acknowledge all ({{ $activeAlerts->count() }})</button>
                        </form>
                    @endcan
                @endif
            </div>
            @forelse ($activeAlerts as $alert)
                <div class="card shadow-sm border-danger mb-2">
                    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div>
                            @include('trends.partials.alert-summary', ['alert' => $alert])
                            <div class="small text-muted">Raised {{ $alert->triggered_at->format('j M Y, H:i') }}</div>
                        </div>
                        @can('acknowledge', $alert)
                            <form method="POST" action="{{ route('alerts.acknowledge', $alert) }}">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-danger">Acknowledge</button>
                            </form>
                        @endcan
                    </div>
                </div>
            @empty
                <p class="text-muted">No active alerts.</p>
            @endforelse
        </div>
        <div class="col-lg-5">
            <h2 class="h5">Recently acknowledged</h2>
            @forelse ($acknowledgedAlerts as $alert)
                <div class="border rounded bg-white p-2 mb-2 small">
                    @include('trends.partials.alert-summary', ['alert' => $alert, 'compact' => true])
                    <div class="text-muted">Acknowledged by {{ $alert->acknowledgedBy?->name ?? 'a former user' }} on {{ $alert->acknowledged_at->format('j M Y, H:i') }}</div>
                </div>
            @empty
                <p class="text-muted">None yet.</p>
            @endforelse
        </div>
    </div>
@endsection

@if ($latest !== null)
    @push('scripts')
        <script type="application/json" id="trend-data">@json($chart)</script>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const data = JSON.parse(document.getElementById('trend-data').textContent);
                // Categorical slots 1–6 in fixed order (one per domain); red is reserved for "declining" (FR-21).
                const seriesColours = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300'];
                const alertColour = '#dc3545';
                const textColour = '#52514e';
                const gridColour = 'rgba(0, 0, 0, 0.08)';
                const lastIndex = data.labels.length - 1;

                const baseOptions = {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    scales: {
                        x: { grid: { display: false }, ticks: { color: textColour } },
                    },
                    plugins: { legend: { position: 'bottom', labels: { color: textColour, usePointStyle: true } } },
                };

                new Chart(document.getElementById('domain-trend-chart'), {
                    type: 'line',
                    data: {
                        labels: data.labels,
                        datasets: data.datasets.map((dataset, index) => {
                            const colour = dataset.declining ? alertColour : seriesColours[index];

                            return {
                                label: dataset.label + (dataset.declining ? ' ▼ declining' : ''),
                                data: dataset.data,
                                borderColor: colour,
                                backgroundColor: colour,
                                borderWidth: 2,
                                borderDash: dataset.declining ? [6, 4] : [],
                                pointStyle: dataset.declining ? 'triangle' : 'circle',
                                pointRadius: (context) => dataset.declining && context.dataIndex === lastIndex ? 7 : 4,
                                pointHoverRadius: 8,
                                spanGaps: true,
                                tension: 0,
                            };
                        }),
                    },
                    options: {
                        ...baseOptions,
                        scales: {
                            ...baseOptions.scales,
                            y: { min: 0, max: 3, ticks: { stepSize: 0.5, color: textColour }, grid: { color: gridColour }, title: { display: true, text: 'Domain score (0–3)', color: textColour } },
                        },
                        plugins: {
                            ...baseOptions.plugins,
                            tooltip: { callbacks: { label: (context) => `${context.dataset.label}: ${context.parsed.y === null ? 'Unanswered' : context.parsed.y.toFixed(2)}` } },
                        },
                    },
                });

                new Chart(document.getElementById('overall-trend-chart'), {
                    type: 'line',
                    data: {
                        labels: data.labels,
                        datasets: [{
                            label: 'Overall score',
                            data: data.overall,
                            borderColor: '#212529',
                            backgroundColor: '#212529',
                            borderWidth: 2,
                            pointRadius: 4,
                            pointHoverRadius: 8,
                            tension: 0,
                        }],
                    },
                    options: {
                        ...baseOptions,
                        scales: {
                            ...baseOptions.scales,
                            y: { min: 0, max: 1, ticks: { stepSize: 0.25, color: textColour }, grid: { color: gridColour }, title: { display: true, text: 'Overall score (0–1)', color: textColour } },
                        },
                        plugins: {
                            legend: { display: false },
                            tooltip: { callbacks: { label: (context) => `Overall score: ${context.parsed.y.toFixed(3)}` } },
                        },
                    },
                });
            });
        </script>
    @endpush
@endif
