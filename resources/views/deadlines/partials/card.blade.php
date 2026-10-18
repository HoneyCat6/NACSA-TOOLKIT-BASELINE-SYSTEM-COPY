@php($status = $deadline->deriveStatus())
@php($daysRemaining = $deadline->daysRemaining())
@php($sentLeadDays = $deadline->sentReminderLeadDays())
<div class="col-md-6 col-xl-4">
    <div @class(['card shadow-sm h-100', 'border-danger' => $status === App\Enums\DeadlineStatus::Overdue, 'border-warning' => $status === App\Enums\DeadlineStatus::DueSoon])>
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                <h3 class="h6 mb-0">{{ $deadline->title }}</h3>
                <span class="badge {{ $status->badgeClass() }}">{{ $status->label() }}</span>
            </div>

            <div @class(['fs-5 fw-semibold', 'text-danger' => $daysRemaining < 0])>
                @include('deadlines.partials.days-remaining')
            </div>
            <div class="small text-muted mb-2">Due {{ $deadline->due_date->format('l, j M Y') }}</div>

            @if ($deadline->description)
                <p class="small mb-2">{{ $deadline->description }}</p>
            @endif

            <div class="small mb-2">
                <span class="text-muted">Reminders:</span>
                @foreach ($deadline->leadDays() as $leadDay)
                    @if (in_array($leadDay, $sentLeadDays, true))
                        <span class="badge text-bg-primary" title="Sent">{{ $leadDay }}d · sent</span>
                    @else
                        <span class="badge text-bg-light border">{{ $leadDay }}d</span>
                    @endif
                @endforeach
            </div>

            <dl class="row small mb-0">
                <dt class="col-5 fw-normal text-muted">Primary contact</dt>
                <dd class="col-7 mb-1">{{ $deadline->officer->name }}</dd>
                <dt class="col-5 fw-normal text-muted">Escalation contact</dt>
                <dd class="col-7 mb-1">
                    {{ $deadline->secondaryContact?->name ?? 'None' }}
                    @if ($deadline->wasEscalated())
                        <span class="badge text-bg-danger">Escalated</span>
                    @endif
                </dd>
                <dt class="col-5 fw-normal text-muted">Acknowledged</dt>
                <dd class="col-7 mb-0">
                    @if ($deadline->isAcknowledged())
                        {{ $deadline->acknowledged_at->format('j M Y') }}{{ $deadline->acknowledgedBy ? ' by '.$deadline->acknowledgedBy->name : '' }}
                    @else
                        Not yet
                    @endif
                </dd>
            </dl>
        </div>
        <div class="card-footer bg-white d-flex flex-wrap gap-1">
            <form method="POST" action="{{ route('deadlines.met', $deadline) }}" x-data
                  @submit="if (! confirm({{ Js::from("Mark \"{$deadline->title}\" as met? No further reminders or escalation will be sent.") }})) $event.preventDefault()">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-success">Mark met</button>
            </form>
            @unless ($deadline->isAcknowledged())
                <form method="POST" action="{{ route('deadlines.acknowledge', $deadline) }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-primary">Acknowledge</button>
                </form>
            @endunless
            <a href="{{ route('deadlines.edit', $deadline) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
            @can('delete', $deadline)
                <form method="POST" action="{{ route('deadlines.destroy', $deadline) }}" x-data
                      @submit="if (! confirm({{ Js::from("Delete \"{$deadline->title}\"?") }})) $event.preventDefault()">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                </form>
            @endcan
        </div>
    </div>
</div>
