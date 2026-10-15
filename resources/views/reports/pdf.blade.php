{{-- Executive summary PDF (FR-33). Rendered by DomPDF from the stored payload only: CSS 2.1, tables, no scripts. --}}
@php
    $assessmentDate = \Illuminate\Support\Carbon::parse($report['assessment_date']);
    $generatedAt = \Illuminate\Support\Carbon::parse($report['generated_at']);
    $band = App\Enums\Band::from($report['overall_band']);
    $status = App\Enums\ComplianceStatus::from($report['compliance_status']);
    $elevatedCount = collect($report['domains'])->whereIn('risk', [App\Enums\RiskCategory::Critical->value, App\Enums\RiskCategory::High->value])->count();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Executive summary · {{ $report['cycle_label'] }}</title>
    <style>
        @page { margin: 22mm 18mm 20mm 18mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 9.5pt; color: #212529; line-height: 1.45; }
        h1, h2, h3 { margin: 0; font-weight: bold; }
        h2 { font-size: 13pt; margin: 0 0 8pt; padding-bottom: 4pt; border-bottom: 1.5pt solid #212529; }
        h3 { font-size: 10.5pt; margin: 0 0 3pt; }
        p { margin: 0 0 6pt; }
        .section { margin-bottom: 18pt; }
        .muted { color: #6c757d; }
        .small { font-size: 8pt; }
        .badge { display: inline-block; padding: 2pt 6pt; border-radius: 3pt; font-weight: bold; font-size: 8.5pt; }
        table.grid { width: 100%; border-collapse: collapse; }
        table.grid th { text-align: left; font-size: 8pt; text-transform: uppercase; color: #495057; background: #f1f3f5; padding: 5pt 6pt; border-bottom: 1pt solid #ced4da; }
        table.grid td { padding: 6pt; border-bottom: 0.5pt solid #dee2e6; vertical-align: top; }
        table.grid tr { page-break-inside: avoid; }
        .footer { position: fixed; bottom: -12mm; left: 0; right: 0; font-size: 7.5pt; color: #6c757d; border-top: 0.5pt solid #dee2e6; padding-top: 3pt; }
        .footer .page:after { content: counter(page); }
        .cover { padding-top: 45mm; }
        .cover .org { font-size: 12pt; text-transform: uppercase; letter-spacing: 1pt; color: #495057; }
        .cover h1 { font-size: 26pt; margin: 6pt 0 4pt; }
        .cover .cycle { font-size: 15pt; margin-bottom: 26pt; }
        .cover table td { padding: 5pt 12pt 5pt 0; vertical-align: top; }
        .callout { border: 1pt solid #dee2e6; border-left: 4pt solid; padding: 8pt 10pt; margin-bottom: 8pt; }
        .narrative { margin-bottom: 9pt; page-break-inside: avoid; }
        .page-break { page-break-after: always; }
    </style>
</head>
<body>
    <div class="footer">
        <table style="width: 100%;">
            <tr>
                <td>{{ $report['organisation'] }} · {{ $report['cycle_label'] }} executive summary · version {{ $report['version'] }}@if ($report['footer']) · {{ $report['footer'] }}@endif</td>
                <td style="text-align: right; white-space: nowrap;">Page <span class="page"></span></td>
            </tr>
        </table>
    </div>

    {{-- Cover page --}}
    <div class="cover page-break">
        <div class="org">{{ $report['organisation'] }}</div>
        <h1>Cybersecurity Executive Summary</h1>
        <div class="cycle">{{ $report['cycle_label'] }}</div>

        <table>
            <tr>
                <td class="muted">Assessment date</td>
                <td><strong>{{ $assessmentDate->format('j F Y') }}</strong></td>
            </tr>
            <tr>
                <td class="muted">Overall posture</td>
                <td>
                    <span class="badge" style="background: {{ $band->severity()->colour() }}; color: {{ $band->severity()->textColour() }};">{{ $band->value }}</span>
                </td>
            </tr>
            <tr>
                <td class="muted">Cyber Security Act 2024</td>
                <td>
                    <span class="badge" style="background: {{ $status->severity()->colour() }}; color: {{ $status->severity()->textColour() }};">{{ $status->value }}</span>
                    <span class="small muted">indicative*</span>
                </td>
            </tr>
            <tr>
                <td class="muted">Report</td>
                <td>Version {{ $report['version'] }} · generated {{ $generatedAt->format('j F Y, H:i') }} by {{ $report['generated_by'] }}</td>
            </tr>
        </table>

        @if ($report['footer'])
            <p style="margin-top: 30pt;"><strong>{{ $report['footer'] }}</strong></p>
        @endif
    </div>

    {{-- At a glance --}}
    <div class="section">
        <h2>At a glance</h2>
        <div class="callout" style="border-left-color: {{ $band->severity()->colour() }};">
            <p>
                Overall cybersecurity posture is <strong>{{ $band->value }}</strong>.
                {{ $elevatedCount === 0 ? 'No area' : $elevatedCount.' of 6 areas' }} {{ $elevatedCount === 1 ? 'is' : 'are' }} at critical or high risk.
            </p>
            <p style="margin: 0;">
                Indicative Cyber Security Act 2024 status: <strong>{{ $status->value }}</strong>. {{ $status->summary() }}*
            </p>
        </div>
    </div>

    {{-- FR-30 domain risk table --}}
    <div class="section">
        <h2>Risk by area</h2>
        <table class="grid">
            <thead>
                <tr>
                    <th style="width: 48%;">Area</th>
                    <th style="width: 26%;">Risk</th>
                    <th>Since last assessment</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($report['domains'] as $domain)
                    @php($risk = App\Enums\RiskCategory::from($domain['risk']))
                    <tr>
                        <td>
                            <strong>{{ $domain['area'] }}</strong>
                            <div class="small muted">NCSB domain: {{ $domain['domain'] }}</div>
                        </td>
                        <td>
                            <span class="badge" style="background: {{ $risk->severity()->colour() }}; color: {{ $risk->severity()->textColour() }};">{{ $risk->value }}</span>
                        </td>
                        <td>{{ $domain['change'] ?? 'Not available' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- FR-31 narratives --}}
    <div class="section">
        <h2>What this means for the organisation</h2>
        @foreach ($report['domains'] as $domain)
            <div class="narrative">
                <h3>{{ $domain['area'] }} <span class="small muted">· {{ $domain['risk'] }}</span></h3>
                <p>{{ $domain['narrative'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- FR-32 prioritised actions --}}
    <div class="section">
        <h2>Priority actions</h2>
        @if ($report['actions'] === [])
            <p>Every assessed control has reached the highest level. Maintain current practice and monitor for degradation.</p>
        @else
            <p class="small muted">
                Ranked by distance from the highest level. Where two gaps are equal, governance and incident response come first.
                Each action is what the control needs to reach its next level.
            </p>
            <table class="grid">
                <thead>
                    <tr>
                        <th style="width: 4%;">#</th>
                        <th style="width: 26%;">Control</th>
                        <th style="width: 22%;">Current → next level</th>
                        <th>What is required</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($report['actions'] as $action)
                        <tr>
                            <td><strong>{{ $action['rank'] }}</strong></td>
                            <td>
                                <strong>{{ $action['name'] }}</strong>
                                <div class="small muted">{{ $action['code'] }} · {{ $action['area'] }}</div>
                            </td>
                            <td>{{ $action['level_label'] }} ({{ $action['level'] }})<br>→ {{ $action['target_label'] }} ({{ $action['level'] + 1 }})</td>
                            <td>{!! nl2br(e($action['action'])) !!}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    @if ($report['unanswered'] !== [])
        <div class="section">
            <h2>Not assessed in this cycle</h2>
            <p>
                Every question for these controls was left blank. They are not ranked above, count as not implemented
                in the overall posture, and should be completed in the next self-assessment.
            </p>
            <ul>
                @foreach ($report['unanswered'] as $element)
                    <li>{{ $element['name'] }} <span class="small muted">({{ $element['code'] }})</span></li>
                @endforeach
            </ul>
        </div>
    @endif

    <p class="small muted">
        * The Cyber Security Act 2024 status is indicative only. It applies this system's rule to the self-assessment
        results: <em>Non-compliant</em> if any area is at critical risk or the overall posture is Initial;
        <em>Compliant</em> if the overall posture is Advanced, every area is at moderate risk or better and every control
        was assessed; otherwise <em>Partially compliant</em>. It is not a legal determination of compliance.
    </p>
    <p class="small muted">
        Risk by area is derived from the average maturity of the controls in each area of the NCSB v1.4 self-assessment.
        Overall posture is the share of the 33 controls with at least one practice in place.
    </p>
</body>
</html>
