<section class="card shadow-sm mb-4" aria-labelledby="forecast-heading">
    <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h2 id="forecast-heading" class="h5 mb-1">Next assessment outlook</h2>
            <div class="small text-muted">Where each area is heading if recent trends continue.</div>
        </div>
        <span class="badge text-bg-light border">Four-month planning estimate</span>
    </div>

    @if ($forecast['unavailable'])
        <div class="card-body">
            <p class="mb-1 fw-semibold">Forecast not available yet</p>
            <p class="text-muted mb-0">{{ $forecast['unavailable'] }}</p>
        </div>
    @else
        <div class="card-body border-bottom">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <div class="small text-muted">Estimated position on</div>
                    <div class="h4 mb-1">{{ $forecast['date']->format('j M Y') }}</div>
                    <div class="small text-muted">Three assessments per year, four months apart.</div>
                </div>
                <div class="d-flex flex-wrap gap-4">
                    <div><div class="h4 mb-0 text-success">{{ $forecast['improving'] }}</div><div class="small">{{ Str::plural('area', $forecast['improving']) }} improving</div></div>
                    <div><div class="h4 mb-0 text-danger">{{ $forecast['declining'] }}</div><div class="small">{{ Str::plural('area', $forecast['declining']) }} declining</div></div>
                    <div><div class="h4 mb-0 text-secondary">{{ $forecast['stable'] }}</div><div class="small">{{ Str::plural('area', $forecast['stable']) }} stable</div></div>
                </div>
            </div>
        </div>
        @php
            $forecastChartData = [
                'currentDate' => $forecast['sources'][array_key_last($forecast['sources'])]['date'],
                'forecastDate' => $forecast['date']->format('j M Y'),
                'rows' => array_map(fn (array $row): array => [
                    'area' => $row['area'],
                    'current' => $row['current'],
                    'estimate' => $row['estimate'],
                    'currentRisk' => $row['current_risk']->value,
                    'estimatedRisk' => $row['estimated_risk']?->value,
                    'colour' => $row['estimated_risk']?->severity()->colour(),
                    'direction' => $row['direction'],
                ], $forecast['rows']),
            ];
        @endphp
        <div class="card-body">
            <h3 id="forecast-chart-heading" class="h6">Current vs next assessment outlook</h3>
            <p id="forecast-chart-description" class="small text-muted mb-2">
                Grey bars: current, {{ $forecastChartData['currentDate'] }}. Coloured bars: forecast, {{ $forecastChartData['forecastDate'] }}.
                Each bar shows its score and risk category; the detailed table is available below.
            </p>
            <div class="forecast-chart-scroll">
                <div class="forecast-chart-container">
                    <canvas id="executive-forecast-chart" role="img" aria-labelledby="forecast-chart-heading" aria-describedby="forecast-chart-description">
                        Current and forecast maturity scores for six areas, on a scale of 0 to 3. See the detailed forecast table below for all values.
                    </canvas>
                </div>
            </div>
            <script type="application/json" id="executive-forecast-data">@json($forecastChartData)</script>
        </div>
        <details class="border-top">
            <summary class="p-3 fw-semibold">View detailed forecast table</summary>
            <div class="table-responsive">
            <table class="table align-middle mb-0">
                <caption class="visually-hidden">Current and estimated domain maturity scores on a zero to three scale, with indicative risk categories.</caption>
                <thead class="table-light">
                    <tr>
                        <th scope="col">Area</th>
                        <th scope="col">Current</th>
                        <th scope="col">Next estimate</th>
                        <th scope="col">Outlook</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($forecast['rows'] as $row)
                        <tr>
                            <th scope="row" class="fw-semibold">{{ $row['area'] }}</th>
                            <td>
                                <div class="text-nowrap mb-1">{{ $row['current'] === null ? 'Not assessed' : number_format($row['current'], 2).' / 3' }}</div>
                                @include('partials.severity-badge', ['severity' => $row['current_risk']->severity(), 'label' => $row['current_risk']->value])
                            </td>
                            <td>
                                @if ($row['estimate'] !== null)
                                    <div class="fw-semibold text-nowrap mb-1">{{ number_format($row['estimate'], 2) }} / 3</div>
                                    @include('partials.severity-badge', ['severity' => $row['estimated_risk']->severity(), 'label' => $row['estimated_risk']->value])
                                @else
                                    <span class="small text-muted">Insufficient comparable scores</span>
                                @endif
                            </td>
                            <td class="small">
                                @if ($row['direction'])
                                    <span @class(['fw-semibold', 'text-success' => $row['direction'] === 'Improving', 'text-danger' => $row['direction'] === 'Declining', 'text-secondary' => $row['direction'] === 'Stable'])>{{ $row['direction'] }}</span>
                                    @if ($row['risk_change'])
                                        <div class="mt-1">{{ $row['risk_change'] }}</div>
                                    @else
                                        <div class="text-muted mt-1">Remains {{ $row['estimated_risk']->value }} risk</div>
                                    @endif
                                @else
                                    <span class="text-muted">A score is missing from at least one of the three assessments.</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        </details>
        <div class="card-footer bg-white small text-muted">
            <p class="mb-2">Assumes recent trends continue. Uses the average daily score change across the latest three assessments, projected four months forward and limited to 0–3. Indicative estimate, not a prediction of incidents or legal compliance.</p>
            <details>
                <summary>Assessments used</summary>
                <ul class="mt-2 mb-0">
                    @foreach ($forecast['sources'] as $source)
                        <li>{{ $source['label'] }} · {{ $source['date'] }}</li>
                    @endforeach
                </ul>
            </details>
        </div>
    @endif
</section>
