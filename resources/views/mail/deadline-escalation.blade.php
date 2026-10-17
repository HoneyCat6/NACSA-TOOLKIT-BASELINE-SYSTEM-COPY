{{-- Plain-text mail: output is unescaped so "&" in titles and names stays intact. --}}
Overdue compliance deadline

"{!! $deadline->title !!}" was due on {{ $deadline->due_date->format('l, j M Y') }} and is now {{ $daysOverdue }} {{ Str::plural('day', $daysOverdue) }} overdue.

No assessment has been uploaded for it and the reminders were not acknowledged by the primary contact, {!! $deadline->officer->name !!} ({{ $deadline->officer->email }}).

You are receiving this because you are the escalation contact for this deadline. Please follow up with the primary contact.

{{ $link }}
