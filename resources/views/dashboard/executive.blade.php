@extends('layouts.app')

@section('title', 'Executive dashboard')

@section('content')
    <h1 class="h3 mb-3">Executive dashboard</h1>

    @if ($latest)
        <div class="row g-3 mb-4">
            <div class="col-lg-5">
                <div class="card shadow-sm h-100 border-start border-4" style="border-left-color: {{ $complianceStatus->severity()->colour() }} !important;">
                    <div class="card-body">
                        <div class="small text-muted mb-1">Overall compliance posture · {{ $latest->cycle_label }}, assessed {{ $latest->assessment_date->format('j M Y') }}</div>
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                            @include('partials.severity-badge', [
                                'severity' => $complianceStatus->severity(),
                                'label' => $complianceStatus->value,
                                'class' => 'fs-5',
                            ])
                            <span class="small text-muted">Cyber Security Act 2024 (indicative)</span>
                        </div>
                        <p class="mb-3">{{ $complianceStatus->summary() }}</p>
                        <div>
                            Maturity band
                            @include('partials.severity-badge', [
                                'severity' => $latest->overall_band->severity(),
                                'label' => $latest->overall_band->value,
                            ])
                            <span class="ms-1">score <strong>{{ $latest->overall_score }}</strong></span>
                        </div>
                        <div class="small text-muted mt-1">Share of the 33 baseline controls with at least one measure in place (0–1).</div>
                        <div class="small text-muted mt-2">This status is an indicative reading of the self-assessment, not a legal determination.</div>
                    </div>
                </div>
            </div>

            <div class="col-lg-7 d-flex flex-column gap-3">
                <div class="card shadow-sm">
                    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
                        @if ($latestReport)
                            <div>
                                <div class="small text-muted">Latest executive report</div>
                                <div class="fw-semibold">{{ $latestReport->assessment->cycle_label }}</div>
                                <div class="small text-muted">Generated {{ $latestReport->generated_at->format('j M Y, H:i') }}</div>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="{{ route('reports.download', $latestReport) }}" class="btn btn-primary">Download PDF</a>
                                <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary">All reports</a>
                            </div>
                        @else
                            <div>
                                <div class="small text-muted">Executive report</div>
                                <div>No executive report has been generated yet.</div>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="row g-3 flex-grow-1">
                    <div class="col-md-4">
                        <a href="{{ route('heatmap.index') }}" class="card shadow-sm h-100 text-decoration-none text-body">
                            <div class="card-body">
                                <div class="fw-semibold">Control heatmap →</div>
                                <div class="small text-muted">Where each of the 33 controls stands, and what to fix first</div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-4">
                        <a href="{{ route('simulator.index') }}" class="card shadow-sm h-100 text-decoration-none text-body">
                            <div class="card-body">
                                <div class="fw-semibold">Scenario simulator →</div>
                                <div class="small text-muted">See how planned improvements would change the posture</div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-4">
                        <a href="{{ route('trends.index') }}" class="card shadow-sm h-100 text-decoration-none text-body">
                            <div class="card-body">
                                <div class="fw-semibold">Trends →</div>
                                <div class="small text-muted">How each area has changed across assessment cycles</div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h2 class="h5 mb-0">Risk by area</h2>
                <div class="small text-muted">Business risk in each of the six baseline areas for {{ $latest->cycle_label }}, with the change since the previous cycle.</div>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Area</th>
                            <th scope="col">Risk</th>
                            <th scope="col" class="text-nowrap">Since last cycle</th>
                            <th scope="col">What it means</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($riskProfile as $domain)
                            @php($risk = App\Enums\RiskCategory::from($domain['risk']))
                            <tr>
                                <th scope="row" class="fw-semibold">
                                    {{ $domain['area'] }}
                                    <div class="small text-muted fw-normal">NCSB {{ $domain['domain'] }}</div>
                                </th>
                                <td>@include('partials.severity-badge', ['severity' => $risk->severity(), 'label' => $risk->value])</td>
                                <td class="text-nowrap small">
                                    @switch($domain['change'])
                                        @case('Improved')
                                            <span class="fw-semibold text-success">▲ Improved</span>
                                            @break
                                        @case('Declined')
                                            <span class="fw-semibold text-danger">▼ Declined</span>
                                            @break
                                        @case('No change')
                                            <span class="text-muted">No change</span>
                                            @break
                                        @default
                                            <span class="text-muted">—</span>
                                    @endswitch
                                </td>
                                <td class="small">{{ $domain['narrative'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="card shadow-sm">
            <div class="card-body text-center py-5">
                <p class="lead mb-1">No assessment results yet</p>
                <p class="text-muted mb-3">
                    Your compliance posture, risk by area and the executive report appear here once a Compliance Officer
                    uploads the first completed self-assessment.
                </p>
                @if ($officerEmails !== [])
                    <a href="mailto:{{ implode(',', $officerEmails) }}?subject={{ rawurlencode('NCSB self-assessment: first upload') }}" class="btn btn-primary">
                        Email the Compliance {{ Str::plural('Officer', count($officerEmails)) }}
                    </a>
                @else
                    <p class="small text-muted mb-0">No Compliance Officer account is active yet. Ask your system administrator to create one.</p>
                @endif
            </div>
        </div>
    @endif
@endsection
