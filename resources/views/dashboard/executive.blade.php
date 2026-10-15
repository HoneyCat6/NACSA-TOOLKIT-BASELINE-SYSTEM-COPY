@extends('layouts.app')

@section('title', 'Executive dashboard')

@section('content')
    <h1 class="h3 mb-3">Executive dashboard</h1>

    @if ($latest)
        @include('dashboard.partials.latest-assessment')

        <div class="card shadow-sm mt-3">
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
    @else
        <div class="card shadow-sm">
            <div class="card-body text-center py-5">
                <p class="lead mb-1">No assessment results yet</p>
                <p class="text-muted mb-0">Your compliance posture will appear once the first assessment cycle has been uploaded.</p>
            </div>
        </div>
    @endif
@endsection
