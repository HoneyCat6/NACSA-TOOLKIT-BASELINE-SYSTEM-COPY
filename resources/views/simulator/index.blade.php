@extends('layouts.app')

@section('title', 'Scenario simulator')

@push('styles')
    <style>
        @media (min-width: 992px) {
            .sim-posture { position: sticky; top: 1rem; max-height: calc(100vh - 2rem); overflow-y: auto; }
        }
        .sim-bar { position: relative; height: 10px; background: var(--bs-secondary-bg); border-radius: 5px; }
        .sim-bar-fill { position: absolute; inset: 0 auto 0 0; border-radius: 5px; transition: width 0.15s ease-out; }
        .sim-bar-baseline { position: absolute; top: -3px; bottom: -3px; width: 2px; background: #212529; }
        .sim-domain + .sim-domain { border-top: 1px solid var(--bs-border-color); }
        .sim-cells { display: grid; grid-template-columns: repeat(10, minmax(0, 1fr)); gap: 6px; }
        .sim-cell {
            height: 52px;
            border: 1px solid rgba(0, 0, 0, 0.08);
            border-radius: 6px;
            padding: 4px 6px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            text-align: left;
            width: 100%;
            position: relative;
        }
        .sim-cell.is-changed { outline: 2px solid #212529; outline-offset: 1px; }
        .sim-cell-id { font-size: 11px; font-weight: 600; }
        .sim-cell-score { font-size: 18px; font-weight: 700; line-height: 1; }
        .sim-cell-was { position: absolute; right: 6px; bottom: 4px; font-size: 10px; }
        .sim-row { display: flex; align-items: center; gap: 0.75rem; padding: 0.35rem 0.5rem; border-radius: 6px; }
        .sim-row.is-changed { background: var(--bs-primary-bg-subtle); }
        .sim-row-name { flex: 1 1 auto; min-width: 0; }
        .sim-row-slider { flex: 0 0 150px; }
        .sim-row-level { flex: 0 0 8.5rem; }
        @media (max-width: 767.98px) {
            .sim-cells { grid-template-columns: repeat(5, minmax(0, 1fr)); }
            .sim-row { flex-wrap: wrap; }
            .sim-row-name { flex-basis: 100%; }
        }
    </style>
@endpush

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">Scenario simulator</h1>
            <p class="text-muted small mb-0">Model hypothetical maturity levels and see the projected posture. Nothing here changes assessment data.</p>
        </div>
        @if ($baseline)
            <form method="GET" action="{{ route('simulator.index') }}" class="d-flex align-items-center gap-2">
                <label for="baseline" class="form-label small text-muted mb-0">Baseline</label>
                <select id="baseline" name="baseline" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                    @foreach ($cycles as $option)
                        <option value="{{ $option->id }}" @selected($option->id === $baseline->id)>
                            {{ $option->cycle_label }} — {{ $option->assessment_date->format('j M Y') }}
                        </option>
                    @endforeach
                </select>
            </form>
        @endif
    </div>

    @if (! $baseline)
        <div class="card shadow-sm">
            <div class="card-body text-center py-5">
                <h2 class="h5">No assessments yet</h2>
                <p class="text-muted mb-3">The simulator needs a validated NCSB assessment to use as its baseline.</p>
                @can('create', App\Models\Assessment::class)
                    <a href="{{ route('assessments.upload') }}" class="btn btn-primary">Upload an assessment</a>
                @endcan
            </div>
        </div>
    @else
        @if ($session)
            <div class="alert alert-secondary d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    Scenario <strong>{{ $session->scenario_name }}</strong>
                    <span class="small text-muted">· saved {{ $session->created_at->format('j M Y, H:i') }} · baseline {{ $baseline->cycle_label }}</span>
                </div>
                <a href="{{ route('simulator.index', ['baseline' => $baseline->id]) }}" class="btn btn-sm btn-outline-secondary">New scenario on this baseline</a>
            </div>
        @endif

        <div class="row g-3"
             x-data="simulator(@js([
                 'baseline' => $state,
                 'rules' => $rules,
                 'initialOverrides' => $initialOverrides,
                 'savedOverrides' => (object) ($session?->modified_responses ?? []),
             ]))">

            {{-- Projected posture panel (PRD §11, Fig 5.4). --}}
            <div class="col-lg-4">
                <div class="sim-posture">
                    <div class="card shadow-sm mb-3">
                        <div class="card-body">
                            <div class="small text-muted mb-1">Projected overall band</div>
                            <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                                <span class="badge fs-5" :style="bandStyle(projected.overall_band)" x-text="projected.overall_band"></span>
                                <span>score <strong x-text="projected.overall_band === 'N/A' ? 'N/A' : projected.overall_score.toFixed(3)"></strong></span>
                                <span class="badge" x-show="projected.overall_delta !== null" :class="deltaClass(projected.overall_delta)" x-text="projected.overall_delta === null ? '' : signed(projected.overall_delta, 3) + ' vs baseline'"></span>
                            </div>
                            <div class="small text-muted">Share of applicable core elements with at least one control implemented (0–1).</div>
                            <div class="small text-muted">
                                Baseline {{ $baseline->cycle_label }}: {{ $baseline->overallScoreLabel() }} ({{ $state['overall_band'] }})
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between small">
                                <span>Elements at Level 3</span>
                                <span>
                                    <strong x-text="projected.elements_at_target"></strong> of {{ $rules['elementCount'] }}
                                    <span class="text-muted">(baseline {{ $state['elements_at_target'] }})</span>
                                </span>
                            </div>
                            <div class="d-flex justify-content-between small">
                                <span>Elements changed</span>
                                <strong x-text="changedCount"></strong>
                            </div>
                        </div>
                    </div>

                    <div class="card shadow-sm mb-3">
                        <div class="card-body">
                            <h2 class="h6 mb-1">Domain scores</h2>
                            <p class="small text-muted mb-3">Average element maturity (0–3). The dark tick marks the baseline.</p>
                            @foreach ($state['domains'] as $domain)
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center small mb-1 gap-2">
                                        <span class="fw-semibold">{{ $domain['label'] }}</span>
                                        <span class="d-flex align-items-center gap-1">
                                            <span class="badge" :style="riskStyle(projected.domain_scores['{{ $domain['value'] }}'])" x-text="riskFor(projected.domain_scores['{{ $domain['value'] }}']).value"></span>
                                            <strong x-text="fixed(projected.domain_scores['{{ $domain['value'] }}'], 2)"></strong>
                                            <span class="badge" :class="deltaClass(projected.domain_deltas['{{ $domain['value'] }}'])" x-text="signed(projected.domain_deltas['{{ $domain['value'] }}'], 2)"></span>
                                        </span>
                                    </div>
                                    <div class="sim-bar" role="img"
                                         :aria-label="'{{ $domain['label'] }} projected ' + fixed(projected.domain_scores['{{ $domain['value'] }}'], 2) + ' of 3, baseline {{ $domain['score'] === null ? 'not assessed' : number_format($domain['score'], 2) }}'">
                                        <div class="sim-bar-fill"
                                             :style="`width:${(projected.domain_scores['{{ $domain['value'] }}'] ?? 0) / 3 * 100}%;background:${riskFor(projected.domain_scores['{{ $domain['value'] }}']).colour}`"></div>
                                        @if ($domain['score'] !== null)
                                            <div class="sim-bar-baseline" style="left:calc({{ $domain['score'] / 3 * 100 }}% - 1px)"></div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="card shadow-sm">
                        <div class="card-body">
                            @if ($errors->any())
                                <div class="alert alert-danger small py-2" role="alert">
                                    @foreach ($errors->all() as $error)
                                        <div>{{ $error }}</div>
                                    @endforeach
                                </div>
                            @endif

                            <form method="POST" action="{{ route('simulator.sessions.store') }}">
                                @csrf
                                <input type="hidden" name="baseline_assessment_id" value="{{ $baseline->id }}">
                                <template x-for="entry in overrideEntries" :key="entry.code">
                                    <input type="hidden" :name="`overrides[${entry.code}]`" :value="entry.level">
                                </template>

                                <label for="scenario_name" class="form-label small">Scenario name</label>
                                <input type="text" id="scenario_name" name="scenario_name" maxlength="255" required
                                       class="form-control form-control-sm mb-2 @error('scenario_name') is-invalid @enderror"
                                       value="{{ old('scenario_name') }}" placeholder="e.g. FY2027 PROTECT investment">

                                <div class="d-flex flex-wrap gap-2">
                                    <button type="submit" class="btn btn-primary btn-sm" :disabled="changedCount === 0">Save scenario</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" @click="reset()" :disabled="changedCount === 0">Reset to baseline</button>
                                    @if ($session)
                                        <button type="button" class="btn btn-outline-secondary btn-sm" @click="revertToSaved()">Revert to saved</button>
                                    @endif
                                </div>
                                <div class="small text-muted mt-2" x-show="changedCount === 0">Move a slider to start a scenario.</div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Maturity-gap grid and slider column. --}}
            <div class="col-lg-8">
                <div class="card shadow-sm mb-3">
                    @foreach ($state['domains'] as $domain)
                        @php($domainElements = collect($state['elements'])->where('domain', $domain['value']))
                        <section class="sim-domain p-3" aria-labelledby="domain-{{ $domain['value'] }}">
                            <div class="d-flex flex-wrap justify-content-between align-items-baseline gap-2 mb-2">
                                <div>
                                    <h2 id="domain-{{ $domain['value'] }}" class="h6 mb-0">{{ $domain['label'] }}</h2>
                                    <div class="small text-muted">{{ $domain['area'] }} · {{ $domainElements->count() }} controls</div>
                                </div>
                                <div class="small text-muted">
                                    baseline {{ $domain['score'] === null ? '–' : number_format($domain['score'], 2) }}
                                    → <strong x-text="fixed(projected.domain_scores['{{ $domain['value'] }}'], 2)"></strong>
                                </div>
                            </div>

                            <div class="sim-cells mb-3">
                                @foreach ($domainElements as $element)
                                    <button type="button" class="sim-cell"
                                            :class="{ 'is-changed': isChanged('{{ $element['code'] }}') }"
                                            :style="cellStyle('{{ $element['code'] }}')"
                                            @click="focusSlider('{{ $element['code'] }}')"
                                            :aria-label="'{{ $element['code'] }}, projected ' + levelText('{{ $element['code'] }}')"
                                            title="{{ $element['code'] }} — {{ $element['name'] }}">
                                        <span class="sim-cell-id">{{ $element['code'] }}</span>
                                        <span class="sim-cell-score" x-text="workingLevel('{{ $element['code'] }}') ?? '–'"></span>
                                        <span class="sim-cell-was" x-show="isChanged('{{ $element['code'] }}')" x-cloak>was {{ $element['level'] ?? '–' }}</span>
                                    </button>
                                @endforeach
                            </div>

                            @foreach ($domainElements as $element)
                                @php($baselineSeverity = $element['level'] === null ? App\Enums\Severity::Unanswered : App\Enums\MaturityLevel::from($element['level'])->severity())
                                <div class="sim-row" :class="{ 'is-changed': isChanged('{{ $element['code'] }}') }">
                                    <label for="slider-{{ $element['code'] }}" class="sim-row-name small mb-0">
                                        <span class="fw-semibold">{{ $element['code'] }}</span> {{ $element['name'] }}
                                    </label>
                                    <span class="small text-muted text-nowrap">
                                        baseline
                                        @include('partials.severity-badge', [
                                            'severity' => $baselineSeverity,
                                            'label' => $element['level'] ?? 'Unanswered',
                                        ])
                                    </span>
                                    <input type="range" min="0" max="3" step="1"
                                           id="slider-{{ $element['code'] }}"
                                           class="form-range sim-row-slider"
                                           :value="levels['{{ $element['code'] }}']"
                                           @input="setLevel('{{ $element['code'] }}', $event.target.value)"
                                           :aria-valuetext="levelText('{{ $element['code'] }}')">
                                    <span class="sim-row-level small d-flex align-items-center gap-1">
                                        <span class="badge" :style="cellStyle('{{ $element['code'] }}')" x-text="levelText('{{ $element['code'] }}')"></span>
                                        <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none"
                                                x-show="isChanged('{{ $element['code'] }}')" x-cloak
                                                @click="revert('{{ $element['code'] }}')"
                                                aria-label="Undo the change to {{ $element['code'] }}" title="Back to baseline">↺</button>
                                    </span>
                                </div>
                            @endforeach
                        </section>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    @if ($sessions->isNotEmpty())
        <div class="card shadow-sm mt-3">
            <div class="card-body">
                <h2 class="h6 mb-3">Your saved scenarios</h2>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">Scenario</th>
                                <th scope="col">Baseline</th>
                                <th scope="col">Projected</th>
                                <th scope="col">Changes</th>
                                <th scope="col">Saved</th>
                                <th scope="col"><span class="visually-hidden">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sessions as $saved)
                                <tr @class(['table-active' => $session?->is($saved)])>
                                    <td><a href="{{ route('simulator.sessions.show', $saved) }}">{{ $saved->scenario_name }}</a></td>
                                    <td class="small">{{ $saved->baseline->cycle_label }}</td>
                                    <td>
                                        @include('partials.severity-badge', [
                                            'severity' => $saved->simulated_band->severity(),
                                            'label' => $saved->simulated_band->value,
                                        ])
                                        <span class="small">{{ $saved->simulated_score ?? 'N/A' }}</span>
                                    </td>
                                    <td class="small">{{ count($saved->modified_responses) }}</td>
                                    <td class="small text-muted">{{ $saved->created_at->format('j M Y, H:i') }}</td>
                                    <td class="text-end">
                                        <form method="POST" action="{{ route('simulator.sessions.destroy', $saved) }}"
                                              onsubmit="return confirm('Delete the scenario “{{ addslashes($saved->scenario_name) }}”?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    <script>
        /**
         * Live projection in the browser (DECISIONS D-23): the same algorithm as SimulationService::project(),
         * with thresholds and colours handed over from the server's enums.
         *
         * `overrides` holds only elements that differ from the baseline (D-24). An element without an override
         * keeps its baseline level and "implemented" (≥ 1 Yes) status (D-21); an Unanswered element without one
         * stays out of its domain average (D-22).
         */
        function simulator({ baseline, rules, initialOverrides, savedOverrides }) {
            // PHP round(): half away from zero.
            const round = (value, places) => {
                const factor = 10 ** places;

                return Math.sign(value) * Math.round((Math.abs(value) + Number.EPSILON) * factor) / factor;
            };
            const byCode = Object.fromEntries(baseline.elements.map((element) => [element.code, element]));
            const bandsHighestFirst = [...rules.bands].sort((a, b) => b.minimum - a.minimum);
            const risksHighestFirst = rules.risks.filter((risk) => risk.minimum !== null).sort((a, b) => b.minimum - a.minimum);
            const notAssessed = rules.risks.find((risk) => risk.minimum === null);

            return {
                levels: {},
                overrides: {},

                init() {
                    this.apply(initialOverrides);
                },

                apply(set) {
                    this.levels = Object.fromEntries(baseline.elements.map((element) => [element.code, element.level ?? 0]));
                    this.overrides = {};
                    Object.entries(set ?? {}).forEach(([code, level]) => this.setLevel(code, level));
                },

                setLevel(code, value) {
                    const element = byCode[code];
                    if (! element) {
                        return;
                    }

                    const level = Math.max(0, Math.min(3, parseInt(value, 10) || 0));
                    this.levels[code] = level;

                    if (element.level !== null && level === element.level) {
                        delete this.overrides[code];
                    } else {
                        this.overrides[code] = level;
                    }
                },

                revert(code) {
                    this.levels[code] = byCode[code].level ?? 0;
                    delete this.overrides[code];
                },

                reset() {
                    this.apply({});
                },

                revertToSaved() {
                    this.apply(savedOverrides);
                },

                focusSlider(code) {
                    const slider = document.getElementById('slider-' + code);
                    slider.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    slider.focus({ preventScroll: true });
                },

                isChanged(code) {
                    return code in this.overrides;
                },

                workingLevel(code) {
                    return this.isChanged(code) ? this.overrides[code] : byCode[code].level;
                },

                get changedCount() {
                    return Object.keys(this.overrides).length;
                },

                get overrideEntries() {
                    return Object.entries(this.overrides).map(([code, level]) => ({ code, level }));
                },

                get projected() {
                    const domainScores = {};
                    const domainDeltas = {};

                    baseline.domains.forEach((domain) => {
                        const levels = baseline.elements
                            .filter((element) => element.domain === domain.value)
                            .map((element) => this.workingLevel(element.code))
                            .filter((level) => level !== null);
                        const score = levels.length === 0 ? null : round(levels.reduce((sum, level) => sum + level, 0) / levels.length, 2);

                        domainScores[domain.value] = score;
                        domainDeltas[domain.value] = score === null || domain.score === null ? null : round(score - domain.score, 2);
                    });

                    const implemented = baseline.elements
                        .filter((element) => this.isChanged(element.code) ? this.overrides[element.code] >= 1 : element.implemented)
                        .length;
                    const overallScore = rules.elementCount === 0 ? null : round(implemented / rules.elementCount, 3);

                    return {
                        domain_scores: domainScores,
                        domain_deltas: domainDeltas,
                        overall_score: overallScore,
                        overall_band: rules.elementCount === 0 ? 'N/A' : (bandsHighestFirst.find((band) => overallScore >= band.minimum) ?? bandsHighestFirst.at(-1)).value,
                        overall_delta: overallScore === null ? null : round(overallScore - baseline.overall_score, 3),
                        elements_at_target: baseline.elements.filter((element) => this.workingLevel(element.code) === 3).length,
                    };
                },

                riskFor(score) {
                    if (score === null) {
                        return notAssessed;
                    }

                    return risksHighestFirst.find((risk) => score >= risk.minimum) ?? risksHighestFirst.at(-1);
                },

                riskStyle(score) {
                    const risk = this.riskFor(score);

                    return `background:${risk.colour};color:${risk.textColour}`;
                },

                bandStyle(value) {
                    const band = rules.bands.find((candidate) => candidate.value === value) ?? rules.levels.unanswered;

                    return `background:${band.colour};color:${band.textColour}`;
                },

                cellStyle(code) {
                    const level = this.workingLevel(code);
                    const style = rules.levels[level === null ? 'unanswered' : level];

                    return `background:${style.colour};color:${style.textColour}`;
                },

                levelText(code) {
                    const level = this.workingLevel(code);

                    return level === null ? rules.levels.unanswered.label : `${level} · ${rules.levels[level].label}`;
                },

                fixed(value, places) {
                    return value === null ? '–' : value.toFixed(places);
                },

                signed(value, places) {
                    if (value === null) {
                        return '–';
                    }

                    return (value > 0 ? '+' : '') + value.toFixed(places);
                },

                deltaClass(value) {
                    if (value === null || value === 0) {
                        return 'text-bg-light border';
                    }

                    return value > 0 ? 'text-bg-success' : 'text-bg-danger';
                },
            };
        }
    </script>
@endpush
