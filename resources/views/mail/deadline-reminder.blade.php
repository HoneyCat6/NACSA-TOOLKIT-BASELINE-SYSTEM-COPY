{{-- Plain-text mail: output is unescaped so "&" in titles and in the signed link's query string stay intact. --}}
Compliance deadline reminder

"{!! $deadline->title !!}" is due {{ $dueIn }}, on {{ $deadline->due_date->format('l, j M Y') }}.
@if ($deadline->description)

{!! $deadline->description !!}
@endif

Uploading the completed NCSB v1.4 assessment for this cycle marks the deadline as met.
@if ($acknowledgeUrl)

If you are already working on it, acknowledge this reminder so the deadline is not escalated{!! $deadline->secondaryContact ? ' to '.$deadline->secondaryContact->name : '' !!} when the due date passes:
{!! $acknowledgeUrl !!}

The link works until the end of the due date.
@else

This deadline was acknowledged on {{ $deadline->acknowledged_at->format('j M Y') }}, so it will not be escalated. Reminders continue until it is met.
@endif

All deadlines:
{{ route('deadlines.index') }}
