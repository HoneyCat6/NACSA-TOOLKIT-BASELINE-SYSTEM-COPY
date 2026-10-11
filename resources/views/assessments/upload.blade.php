@extends('layouts.app')

@section('title', 'Upload assessment')

@section('content')
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0">Upload assessment</h1>
        <span class="badge text-bg-dark">Template: NCSB {{ config('ncsb.template_version') }}</span>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-body">
                    <form method="POST" action="{{ route('assessments.store') }}" enctype="multipart/form-data" novalidate
                          x-data="assessmentUpload({{ config('ncsb.max_upload_kb') }})" @submit="submitting = true">
                        @csrf

                        <label for="file"
                               class="d-block border border-2 rounded-3 p-4 text-center mb-1 @error('file') border-danger @enderror"
                               :class="{ 'border-primary bg-primary-subtle': dragging }"
                               style="border-style: dashed !important; cursor: pointer;"
                               @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false"
                               @drop.prevent="dropped($event)">
                            <span class="d-block fw-semibold">Drop the completed toolkit here, or click to browse</span>
                            <span class="d-block small text-muted">Official template only · max {{ config('ncsb.max_upload_kb') / 1024 }} MB · .xlsx</span>
                            <span class="d-block mt-2 small" x-show="fileName" x-cloak>
                                Selected: <strong x-text="fileName"></strong> (<span x-text="fileSize"></span>)
                            </span>
                        </label>
                        <input id="file" type="file" name="file" accept=".xlsx" class="visually-hidden" x-ref="file" @change="selected()">
                        <div class="small text-danger mb-3" x-show="clientError" x-text="clientError" x-cloak></div>
                        @error('file')
                            <div class="alert alert-danger small py-2 mb-3" role="alert">{{ $message }}</div>
                        @enderror

                        <div class="row g-3 mb-3">
                            <div class="col-sm-7">
                                <label for="cycle_label" class="form-label">Cycle label</label>
                                <input id="cycle_label" type="text" name="cycle_label" value="{{ old('cycle_label') }}" maxlength="50" required
                                       placeholder="e.g. Cycle 2026 H1" class="form-control @error('cycle_label') is-invalid @enderror">
                                @error('cycle_label')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-sm-5">
                                <label for="assessment_date" class="form-label">Assessment date</label>
                                <input id="assessment_date" type="date" name="assessment_date" value="{{ old('assessment_date') }}"
                                       max="{{ today()->toDateString() }}" required
                                       class="form-control @error('assessment_date') is-invalid @enderror">
                                @error('assessment_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary" :disabled="submitting">
                            <span class="spinner-border spinner-border-sm me-1" x-show="submitting" x-cloak aria-hidden="true"></span>
                            <span x-text="submitting ? 'Validating and scoring…' : 'Upload and score'">Upload and score</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <h2 class="h5">Recent cycles</h2>
            @if ($recentAssessments->isEmpty())
                <p class="text-muted">No cycles uploaded yet.</p>
            @else
                @include('assessments.partials.history-table', ['assessments' => $recentAssessments])
                <a href="{{ route('assessments.index') }}" class="d-inline-block small mt-2">View full history</a>
            @endif

            <h2 class="h5 mt-4">Recent rejections</h2>
            @if ($recentRejections->isEmpty())
                <p class="text-muted">No rejected uploads.</p>
            @else
                <ul class="list-group shadow-sm">
                    @foreach ($recentRejections as $rejection)
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between gap-2">
                                <span class="text-break fw-semibold small">{{ $rejection->metadata['filename'] ?? 'Unknown file' }}</span>
                                <span class="badge text-bg-danger align-self-start">Rejected</span>
                            </div>
                            <div class="small">{{ $rejection->metadata['reason'] ?? '' }}</div>
                            <div class="small text-muted">{{ $rejection->created_at->format('j M Y, H:i') }} · {{ $rejection->user?->name }}</div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        function assessmentUpload(maxKb) {
            return {
                dragging: false,
                submitting: false,
                fileName: '',
                fileSize: '',
                clientError: '',

                dropped(event) {
                    this.dragging = false;
                    this.$refs.file.files = event.dataTransfer.files;
                    this.selected();
                },

                selected() {
                    const file = this.$refs.file.files[0];
                    this.fileName = file ? file.name : '';
                    this.fileSize = file ? (file.size / 1024 / 1024).toFixed(2) + ' MB' : '';
                    this.clientError = '';

                    if (file && !file.name.toLowerCase().endsWith('.xlsx')) {
                        this.clientError = 'Only .xlsx files are accepted.';
                    } else if (file && file.size > maxKb * 1024) {
                        this.clientError = 'The file is larger than ' + (maxKb / 1024) + ' MB.';
                    }
                },
            };
        }
    </script>
@endpush
