{{-- Whole days to the due date, in words (FR-44). Expects $daysRemaining from ComplianceDeadline::daysRemaining(). --}}
@if ($daysRemaining < 0)
    {{ -$daysRemaining }} {{ Str::plural('day', -$daysRemaining) }} overdue
@elseif ($daysRemaining === 0)
    Due today
@else
    {{ $daysRemaining }} {{ Str::plural('day', $daysRemaining) }} left
@endif
