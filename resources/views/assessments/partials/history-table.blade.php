<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th scope="col">Cycle</th>
                    <th scope="col">Assessment date</th>
                    <th scope="col">File</th>
                    <th scope="col">Uploaded by</th>
                    <th scope="col">Status</th>
                    <th scope="col">Overall band</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($assessments as $assessment)
                    <tr>
                        <td><a href="{{ route('assessments.show', $assessment) }}" class="fw-semibold">{{ $assessment->cycle_label }}</a>
                        @if ($assessment->sector)
                            <div class="small text-muted">{{ $assessment->sector->name }}</div>
                        @endif</td>
                        <td>{{ $assessment->assessment_date->format('j M Y') }}</td>
                        <td class="text-break small">{{ $assessment->original_filename }}</td>
                        <td>{{ $assessment->creator->name }}</td>
                        <td><span class="badge text-bg-success">Validated</span></td>
                        <td>
                            @include('partials.severity-badge', [
                                'severity' => $assessment->overall_band->severity(),
                                'label' => $assessment->overall_band->value,
                            ])
                            <span class="small text-muted ms-1">{{ $assessment->overallScoreLabel() }}</span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
