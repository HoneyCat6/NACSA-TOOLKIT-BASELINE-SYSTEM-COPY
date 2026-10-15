@extends('layouts.app')

@section('title', 'Executive reports')

@section('content')
    <div class="mb-3">
        <h1 class="h3 mb-1">Executive reports</h1>
        <p class="text-muted small mb-0">
            Board-ready PDF summaries: risk by area, plain-language narratives, the top {{ App\Services\ReportService::ActionLimit }}
            priority actions and an indicative Cyber Security Act 2024 status. Each report is kept exactly as generated.
        </p>
    </div>

    @error('confirm_new_version')
        <div class="alert alert-warning" role="alert">{{ $message }}</div>
    @enderror

    @if ($assessments->isEmpty())
        <div class="card shadow-sm">
            <div class="card-body text-center py-5">
                @if ($canGenerate)
                    <p class="lead mb-1">No assessment cycles yet</p>
                    <p class="text-muted mb-3">Upload a completed NCSB v1.4 toolkit, then generate its executive report here.</p>
                    <a href="{{ route('assessments.upload') }}" class="btn btn-primary">Upload assessment</a>
                @else
                    <p class="lead mb-1">No executive reports yet</p>
                    <p class="text-muted mb-0">Reports appear here once the Compliance Officer generates one.</p>
                @endif
            </div>
        </div>
    @else
        <div class="vstack gap-3">
            @foreach ($assessments as $assessment)
                @php($versions = $assessment->reports->values())
                <div class="card shadow-sm" id="cycle-{{ $assessment->id }}">
                    <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div>
                            <h2 class="h5 mb-0">{{ $assessment->cycle_label }}</h2>
                            <div class="small text-muted">
                                Assessed {{ $assessment->assessment_date->format('j M Y') }} ·
                                @include('partials.severity-badge', [
                                    'severity' => $assessment->overall_band->severity(),
                                    'label' => $assessment->overall_band->value,
                                ])
                            </div>
                        </div>

                        @if ($canGenerate)
                            <form method="POST" action="{{ route('reports.generate', $assessment) }}"
                                  class="d-flex flex-wrap align-items-center gap-2"
                                  x-data="{ submitting: false }" @submit="submitting = true">
                                @csrf
                                @if ($versions->isNotEmpty())
                                    <div class="form-check small mb-0">
                                        <input class="form-check-input" type="checkbox" name="confirm_new_version" value="1"
                                               id="confirm-{{ $assessment->id }}" required>
                                        <label class="form-check-label" for="confirm-{{ $assessment->id }}">
                                            Create version {{ $versions->count() + 1 }} (earlier versions are kept)
                                        </label>
                                    </div>
                                @endif
                                <button type="submit" class="btn btn-sm {{ $versions->isEmpty() ? 'btn-primary' : 'btn-outline-primary' }}" :disabled="submitting">
                                    <span class="spinner-border spinner-border-sm me-1" x-show="submitting" x-cloak aria-hidden="true"></span>
                                    <span x-text="submitting ? 'Generating PDF…' : {{ Js::from($versions->isEmpty() ? 'Generate report' : 'Generate new version') }}">
                                        {{ $versions->isEmpty() ? 'Generate report' : 'Generate new version' }}
                                    </span>
                                </button>
                            </form>
                        @endif
                    </div>

                    @if ($versions->isEmpty())
                        <div class="card-body small text-muted">No report generated for this cycle yet.</div>
                    @else
                        <ul class="list-group list-group-flush">
                            @foreach ($versions->reverse() as $index => $report)
                                <li class="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-2">
                                    <div>
                                        <span class="fw-semibold">Version {{ $index + 1 }}</span>
                                        @if ($loop->first)
                                            <span class="badge text-bg-primary ms-1">Latest</span>
                                        @endif
                                        <div class="small text-muted">
                                            Generated {{ $report->generated_at->format('j M Y, H:i') }} by {{ $report->generator->name }}
                                        </div>
                                    </div>
                                    <a href="{{ route('reports.download', $report) }}" class="btn btn-sm btn-outline-secondary">Download PDF</a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
@endsection
