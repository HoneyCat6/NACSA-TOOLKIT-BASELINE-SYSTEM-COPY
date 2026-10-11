@extends('layouts.app')

@section('title', $assessment->cycle_label)

@section('content')
    @php($trend = $assessment->trend)

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">{{ $assessment->cycle_label }}</h1>
            <p class="text-muted small mb-0">
                Assessed {{ $assessment->assessment_date->format('j M Y') }}
                · uploaded by {{ $assessment->creator->name }} on {{ $assessment->created_at->format('j M Y') }}
                · <span class="text-break">{{ $assessment->original_filename }}</span>
                · <span class="badge text-bg-success">Validated</span>
            </p>
        </div>
        <a href="{{ route('assessments.index') }}" class="btn btn-outline-secondary btn-sm">All cycles</a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-6 col-xl-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="small text-muted mb-1">Overall band</div>
                    @include('partials.severity-badge', [
                        'severity' => $assessment->overall_band->severity(),
                        'label' => $assessment->overall_band->value,
                        'class' => 'fs-5',
                    ])
                    <div class="mt-2">Overall score <strong>{{ $assessment->overall_score }}</strong></div>
                    <div class="small text-muted">Share of the 33 elements with at least one control implemented (0–1)</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="small text-muted mb-1">Change vs previous cycle</div>
                    @if ($trend)
                        @include('assessments.partials.delta', ['delta' => $trend->overall_delta, 'class' => 'fs-5'])
                        <div class="small text-muted mt-2">Overall score compared with {{ $trend->previousAssessment->cycle_label }}</div>
                    @else
                        <div class="fs-5">—</div>
                        <div class="small text-muted mt-2">First cycle — trends start with the next upload</div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="small text-muted mb-1">Unanswered elements</div>
                    <div class="fs-5 fw-semibold">{{ $assessment->unanswered_count }} <span class="fs-6 fw-normal text-muted">of 33</span></div>
                    <div class="small text-muted">Every question blank — listed below</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="small text-muted mb-1">Defaulted cells</div>
                    <div class="fs-5 fw-semibold">{{ $assessment->defaulted_cell_count }}</div>
                    <div class="small text-muted">Blank answers before an element's first "No", treated as No</div>
                </div>
            </div>
        </div>
    </div>

    @if ($unansweredElements->isNotEmpty())
        <div class="alert alert-warning" role="alert">
            <h2 class="h6 alert-heading">{{ $unansweredElements->count() }} unanswered {{ Str::plural('element', $unansweredElements->count()) }}</h2>
            <p class="small mb-2">
                These elements had every question left blank. They are shown as Unanswered, left out of domain scores,
                and count as not implemented in the overall score until a complete file is uploaded.
            </p>
            <ul class="small mb-2">
                @foreach ($unansweredElements as $element)
                    <li><strong>{{ $element->element_code }}</strong> {{ $element->ncsbElement->name }}</li>
                @endforeach
            </ul>
            @can('create', App\Models\Assessment::class)
                <a href="{{ route('assessments.upload') }}" class="btn btn-sm btn-warning">Upload a complete file as a new cycle</a>
            @endcan
        </div>
    @endif

    @if (! empty($assessment->parse_warnings))
        <details class="card shadow-sm mb-4">
            <summary class="card-header bg-white">
                {{ count($assessment->parse_warnings) }} parse {{ Str::plural('warning', count($assessment->parse_warnings)) }}
            </summary>
            <ul class="list-group list-group-flush small">
                @foreach ($assessment->parse_warnings as $warning)
                    <li class="list-group-item">{{ $warning }}</li>
                @endforeach
            </ul>
        </details>
    @endif

    @if ($trend?->alerts->isNotEmpty())
        <div class="alert alert-danger" role="alert">
            <strong>Degradation detected.</strong>
            {{ $trend->alerts->map(fn ($alert) => $alert->domain->value)->join(', ', ' and ') }}
            declined compared with {{ $trend->previousAssessment->cycle_label }}.
            <a href="{{ route('trends.index') }}" class="alert-link">Review on the trend dashboard</a>
        </div>
    @endif

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h2 class="h5 mb-0">Domain scores</h2>
            <div class="small text-muted">Average element maturity within each domain (0–3)</div>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Domain</th>
                        <th scope="col" style="min-width: 220px;">Score (0–3)</th>
                        <th scope="col">Change vs previous</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach (App\Enums\Domain::cases() as $domain)
                        @php($score = $assessment->{$domain->scoreColumn()})
                        <tr>
                            <th scope="row">{{ $domain->value }}</th>
                            <td>
                                @if ($score === null)
                                    <span class="text-muted">Unanswered</span>
                                @else
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-grow-1" role="progressbar" aria-label="{{ $domain->value }} score"
                                             aria-valuenow="{{ $score }}" aria-valuemin="0" aria-valuemax="3" style="height: .5rem;">
                                            <div class="progress-bar bg-secondary" style="width: {{ $score / 3 * 100 }}%"></div>
                                        </div>
                                        <strong style="width: 2.5rem;">{{ $score }}</strong>
                                    </div>
                                @endif
                            </td>
                            <td>
                                @if ($trend && $trend->{$domain->deltaColumn()} !== null)
                                    @include('assessments.partials.delta', ['delta' => $trend->{$domain->deltaColumn()}])
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <h2 class="h5 mb-0">Element results</h2>
            <div class="small text-muted">Levels are computed from the Yes-count against the NCSB v1.4 thresholds — score cells in the file are ignored</div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Code</th>
                        <th scope="col">Element</th>
                        <th scope="col">Domain</th>
                        <th scope="col" class="text-center">Yes</th>
                        <th scope="col">Level</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($elements as $element)
                        <tr>
                            <td class="fw-semibold text-nowrap">{{ $element->element_code }}</td>
                            <td>{{ $element->ncsbElement->name }}</td>
                            <td class="small">{{ $element->domain->value }}</td>
                            <td class="text-center text-nowrap">{{ $element->yes_count }} / {{ $element->ncsbElement->question_count }}</td>
                            <td class="text-nowrap">
                                @include('partials.severity-badge', [
                                    'severity' => $element->severity(),
                                    'label' => $element->maturity_level === null
                                        ? 'Unanswered'
                                        : $element->maturity_level->value.' · '.$element->maturity_level->label(),
                                ])
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
