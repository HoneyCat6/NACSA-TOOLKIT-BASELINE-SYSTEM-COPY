@extends('layouts.app')

@section('title', 'Compliance deadlines')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">Compliance deadlines</h1>
            <p class="text-muted small mb-0">
                Reminders are emailed to the primary contact at each lead time. If the due date passes with no upload and no
                acknowledgement, the escalation contact is alerted. Uploading an assessment meets every open deadline that is not yet overdue.
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('notifications.index') }}" class="btn btn-outline-secondary">Notification history</a>
            <a href="{{ route('deadlines.create') }}" class="btn btn-primary">Add deadline</a>
        </div>
    </div>

    @if ($failedNotificationCount > 0)
        <div class="alert alert-danger d-flex flex-wrap justify-content-between align-items-center gap-2" role="alert">
            <span><strong>{{ $failedNotificationCount }}</strong> deadline {{ Str::plural('email', $failedNotificationCount) }} could not be delivered.</span>
            <a href="{{ route('notifications.index', ['delivery' => 'failed']) }}" class="btn btn-sm btn-danger">View failures</a>
        </div>
    @endif

    @if ($openDeadlines->isEmpty() && $metDeadlines->isEmpty())
        <div class="card shadow-sm">
            <div class="card-body text-center py-5">
                <p class="lead mb-1">No deadlines yet</p>
                <p class="text-muted mb-3">Add the date your next NCSB self-assessment is due, and who should be reminded and alerted.</p>
                <a href="{{ route('deadlines.create') }}" class="btn btn-primary">Add deadline</a>
            </div>
        </div>
    @else
        <h2 class="h5">Open</h2>
        @if ($openDeadlines->isEmpty())
            <p class="text-muted small">Every deadline is met.</p>
        @else
            <div class="row g-3 mb-4">
                @foreach ($openDeadlines as $deadline)
                    @include('deadlines.partials.card', ['deadline' => $deadline])
                @endforeach
            </div>
        @endif

        @if ($metDeadlines->isNotEmpty())
            <h2 class="h5">Met</h2>
            <div class="card shadow-sm">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Deadline</th>
                                <th scope="col">Due</th>
                                <th scope="col">Met</th>
                                <th scope="col">How</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($metDeadlines as $deadline)
                                <tr>
                                    <td class="fw-semibold">{{ $deadline->title }}</td>
                                    <td class="text-nowrap small">{{ $deadline->due_date->format('j M Y') }}</td>
                                    <td class="text-nowrap small">{{ $deadline->met_at->format('j M Y') }}</td>
                                    <td class="small">
                                        @if ($deadline->metByAssessment)
                                            Upload of {{ $deadline->metByAssessment->cycle_label }}
                                        @else
                                            Marked met by hand
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endif
@endsection
