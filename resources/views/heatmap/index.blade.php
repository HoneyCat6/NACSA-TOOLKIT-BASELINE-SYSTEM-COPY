@extends('layouts.app')

@section('title', 'Control effectiveness heatmap')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">Control effectiveness heatmap</h1>
            <p class="text-muted small mb-0">All 33 NCSB control elements by maturity gap from the Level 3 target.</p>
        </div>
        @if ($cycle)
            <form method="GET" action="{{ route('heatmap.index') }}" class="d-flex align-items-center gap-2">
                <label for="cycle" class="form-label small text-muted mb-0">Cycle</label>
                <select id="cycle" name="cycle" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                    @foreach ($cycles as $option)
                        <option value="{{ $option->id }}" @selected($option->id === $cycle->id)>
                            {{ $option->cycle_label }} — {{ $option->assessment_date->format('j M Y') }}
                        </option>
                    @endforeach
                </select>
            </form>
        @endif
    </div>

    @if (! $cycle)
        <div class="card shadow-sm">
            <div class="card-body text-center py-5">
                <h2 class="h5">No assessments yet</h2>
                <p class="text-muted mb-3">The heatmap appears once a validated NCSB assessment has been uploaded.</p>
                @can('create', App\Models\Assessment::class)
                    <a href="{{ route('assessments.upload') }}" class="btn btn-primary">Upload an assessment</a>
                @endcan
            </div>
        </div>
    @else
        <div class="card shadow-sm mb-3">
            <div class="card-body d-flex flex-wrap align-items-center gap-3">
                <div>
                    <span class="small text-muted">Showing</span>
                    <strong>{{ $cycle->cycle_label }}</strong>
                    <span class="text-muted small">· assessed {{ $cycle->assessment_date->format('j M Y') }}</span>
                </div>
                <div class="ms-md-auto">
                    @include('partials.severity-badge', [
                        'severity' => $cycle->overall_band->severity(),
                        'label' => 'Overall band: '.$cycle->overall_band->value,
                    ])
                </div>
            </div>
        </div>

        {{-- Legend — colour is never the only signal (FR-54). --}}
        <div class="d-flex flex-wrap gap-2 mb-3" aria-label="Heatmap legend">
            @foreach (App\Enums\Severity::cases() as $severity)
                @include('partials.severity-badge', ['severity' => $severity, 'label' => $severity->label()])
            @endforeach
        </div>

        <div x-data="{
                open: false,
                loading: false,
                error: false,
                detail: null,
                cycleId: {{ $cycle->id }},
                urlTemplate: @js(route('heatmap.detail', ['code' => '__CODE__'])),
                colours: @js(collect(App\Enums\Severity::cases())->mapWithKeys(fn ($s) => [$s->value => $s->colour()])),
                async show(code) {
                    this.open = true;
                    this.loading = true;
                    this.error = false;
                    this.detail = null;
                    try {
                        const response = await fetch(this.urlTemplate.replace('__CODE__', code) + '?cycle=' + this.cycleId, {
                            headers: { Accept: 'application/json' },
                        });
                        if (! response.ok) { throw new Error('Request failed'); }
                        this.detail = await response.json();
                    } catch (e) {
                        this.error = true;
                    } finally {
                        this.loading = false;
                    }
                },
                close() { this.open = false; },
             }"
             @keydown.escape.window="close()">

            @foreach ($grid as $group)
                <section class="mb-4">
                    <h2 class="h6 mb-1">{{ $group['domain']->label() }}
                        <span class="text-muted fw-normal small">· {{ $group['domain']->areaName() }}</span>
                    </h2>
                    <div class="d-flex flex-nowrap gap-2 overflow-auto pb-2">
                        @foreach ($group['cells'] as $cell)
                            <button type="button"
                                    class="btn border p-2 flex-shrink-0 text-start"
                                    style="width:84px;height:68px;background:{{ $cell['severity']->colour() }};color:{{ $cell['severity']->textColour() }};"
                                    @click="show('{{ $cell['code'] }}')"
                                    aria-label="{{ $cell['code'] }} {{ $cell['name'] }} — {{ $cell['label'] }}, level {{ $cell['level'] ?? 'unanswered' }}">
                                <span class="d-block fw-semibold small">{{ $cell['code'] }}</span>
                                <span class="d-block fs-4 lh-1">{{ $cell['level'] ?? '—' }}</span>
                            </button>
                        @endforeach
                    </div>
                </section>
            @endforeach

            {{-- Drill-down panel (FR-51). --}}
            <div x-show="open" x-cloak class="offcanvas-backdrop fade show" style="z-index:1040" @click="close()"></div>
            <div x-show="open" x-cloak
                 class="position-fixed top-0 end-0 h-100 bg-white shadow-lg p-4 overflow-auto"
                 style="width:min(100%,24rem);z-index:1045"
                 role="dialog" aria-modal="true" aria-label="Element detail">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <h2 class="h5 mb-0" x-text="detail ? detail.code + ' · ' + detail.name : 'Loading…'"></h2>
                    <button type="button" class="btn-close" aria-label="Close" @click="close()"></button>
                </div>

                <template x-if="loading">
                    <p class="text-muted">Loading…</p>
                </template>

                <template x-if="error">
                    <p class="text-danger">Could not load element details.</p>
                </template>

                <template x-if="detail">
                    <div>
                        <span class="badge mb-3" :style="`background:${colours[detail.severity]};color:${detail.severity === 'low' || detail.severity === 'high' || detail.severity === 'unanswered' ? '#212529' : '#fff'}`"
                              x-text="detail.severity_label"></span>

                        <dl class="row mb-0 small">
                            <dt class="col-5 text-muted fw-normal">Domain</dt>
                            <dd class="col-7" x-text="detail.domain"></dd>

                            <dt class="col-5 text-muted fw-normal">Category</dt>
                            <dd class="col-7" x-text="detail.category"></dd>

                            <dt class="col-5 text-muted fw-normal">Current level</dt>
                            <dd class="col-7" x-text="detail.maturity_level === null ? 'Unanswered' : detail.maturity_level + ' (' + detail.level_label + ')'"></dd>

                            <dt class="col-5 text-muted fw-normal">Gap from Level 3</dt>
                            <dd class="col-7" x-text="detail.gap_from_target === null ? '—' : detail.gap_from_target"></dd>

                            <dt class="col-5 text-muted fw-normal">"Yes" answers</dt>
                            <dd class="col-7" x-text="detail.yes_count + ' of ' + detail.question_count"></dd>
                        </dl>

                        <hr>
                        <h3 class="h6">Remediation priority</h3>
                        <p class="small mb-0" x-text="detail.remediation_note"></p>
                    </div>
                </template>
            </div>
        </div>
    @endif
@endsection
