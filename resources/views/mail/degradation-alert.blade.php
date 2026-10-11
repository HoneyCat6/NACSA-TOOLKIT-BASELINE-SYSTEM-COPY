@php($domainAlerts = $trend->alerts->reject->isForElement())
@php($elementAlerts = $trend->alerts->filter->isForElement())
The NCSB v1.4 assessment "{{ $trend->assessment->cycle_label }}" ({{ $trend->assessment->assessment_date->format('j M Y') }}) shows a decline compared with "{{ $trend->previousAssessment->cycle_label }}":
@if ($domainAlerts->isNotEmpty())

Domains:
@foreach ($domainAlerts as $alert)
- {{ $alert->domain->value }}: domain score fell by {{ $alert->score_drop }} (from {{ $trend->previousAssessment->{$alert->domain->scoreColumn()} }} to {{ $trend->assessment->{$alert->domain->scoreColumn()} }}, on a 0–3 scale)
@endforeach
@endif
@if ($elementAlerts->isNotEmpty())

Elements:
@foreach ($elementAlerts as $alert)
- {{ $alert->ncsbElement->code }} {{ $alert->ncsbElement->name }} ({{ $alert->domain->value }}): maturity level fell by {{ (int) $alert->score_drop }}
@endforeach
@endif

Review and acknowledge these alerts on the trend dashboard:
{{ route('trends.index') }}
