<div class="card shadow-sm">
    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <div class="small text-muted">Latest cycle · assessed {{ $latest->assessment_date->format('j M Y') }}</div>
            <div class="fs-5 fw-semibold">{{ $latest->cycle_label }}{{ $latest->sector ? ' · '.$latest->sector->name : '' }}</div>
            <div class="mt-1">
                @include('partials.severity-badge', [
                    'severity' => $latest->overall_band->severity(),
                    'label' => $latest->overall_band->value,
                ])
                <span class="ms-1">overall score <strong>{{ $latest->overallScoreLabel() }}</strong></span>
                <span class="small text-muted">(share of the 33 elements with at least one control implemented)</span>
            </div>
        </div>
        <div class="d-flex gap-2">
            @can('view', $latest)
                <a href="{{ route('assessments.show', $latest) }}" class="btn btn-outline-primary">View results</a>
            @endcan
            <a href="{{ route('trends.index') }}" class="btn btn-outline-secondary">Trends</a>
        </div>
    </div>
</div>
