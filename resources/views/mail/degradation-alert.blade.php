The NCSB v1.4 assessment "{{ $trend->assessment->cycle_label }}" ({{ $trend->assessment->assessment_date->format('j M Y') }}) shows a decline compared with "{{ $trend->previousAssessment->cycle_label }}":

@foreach ($trend->alerts as $alert)
- {{ $alert->domain->value }}: domain score fell by {{ $alert->score_drop }} (from {{ $trend->previousAssessment->{$alert->domain->scoreColumn()} }} to {{ $trend->assessment->{$alert->domain->scoreColumn()} }}, on a 0–3 scale)
@endforeach

Review and acknowledge these alerts on the trend dashboard:
{{ route('trends.index') }}
