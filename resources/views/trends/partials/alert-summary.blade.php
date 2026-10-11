{{-- One degradation alert: a domain score fell, or an element's level fell (DECISIONS D-36). --}}
@php($cycle = $alert->trend->assessment)
@unless ($compact ?? false)
    <span class="badge text-bg-danger me-1">▼ {{ $alert->isForElement() ? 'Element' : 'Domain' }}</span>
@endunless
@if ($alert->isForElement())
    <strong>{{ $alert->ncsbElement->code }}</strong> {{ $alert->ncsbElement->name }}
    <span class="text-muted">({{ $alert->domain->value }})</span>
    fell by {{ (int) $alert->score_drop }} {{ Str::plural('level', (int) $alert->score_drop) }}
@else
    <strong>{{ $alert->domain->value }}</strong> domain score fell by {{ $alert->score_drop }}
@endif
in
@can('view', $cycle)
    <a href="{{ route('assessments.show', $cycle) }}">{{ $cycle->cycle_label }}</a>
@else
    {{ $cycle->cycle_label }}
@endcan
