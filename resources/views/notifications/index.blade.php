@extends('layouts.app')

@section('title', 'Notification history')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">Notification history</h1>
            <p class="text-muted small mb-0">Every deadline reminder and escalation email. Failed emails were retried 3 times before giving up.</p>
        </div>
        <a href="{{ route('deadlines.index') }}" class="btn btn-outline-secondary">Back to deadlines</a>
    </div>

    <form method="GET" action="{{ route('notifications.index') }}" class="card shadow-sm mb-3">
        <div class="card-body row g-2 align-items-end">
            <div class="col-sm-4 col-md-3">
                <label for="type" class="form-label small fw-semibold">Type</label>
                <select id="type" name="type" class="form-select form-select-sm">
                    <option value="">All types</option>
                    @foreach (App\Enums\NotificationType::cases() as $type)
                        <option value="{{ $type->value }}" @selected(($filters['type'] ?? null) === $type->value)>{{ Str::ucfirst($type->value) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-4 col-md-3">
                <label for="delivery" class="form-label small fw-semibold">Delivery</label>
                <select id="delivery" name="delivery" class="form-select form-select-sm">
                    <option value="">Any</option>
                    @foreach (App\Http\Controllers\NotificationController::DeliveryFilters as $delivery)
                        <option value="{{ $delivery }}" @selected(($filters['delivery'] ?? null) === $delivery)>
                            {{ Str::ucfirst($delivery) }}{{ $delivery === 'failed' ? " ({$failedCount})" : '' }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-4 col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                <a href="{{ route('notifications.index') }}" class="btn btn-sm btn-link">Clear</a>
            </div>
        </div>
    </form>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Dispatched</th>
                        <th scope="col">Type</th>
                        <th scope="col">Deadline</th>
                        <th scope="col">Recipient</th>
                        <th scope="col">Lead day</th>
                        <th scope="col">Delivered</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($notifications as $notification)
                        <tr>
                            <td class="text-nowrap small">{{ $notification->dispatched_at->format('j M Y, H:i') }}</td>
                            <td>
                                @if ($notification->type === App\Enums\NotificationType::Escalation)
                                    <span class="badge text-bg-danger">Escalation</span>
                                @else
                                    <span class="badge text-bg-primary">Reminder</span>
                                @endif
                            </td>
                            <td class="small">
                                {{ $notification->deadline->title }}
                                <div class="text-muted">Due {{ $notification->due_date->format('j M Y') }}</div>
                            </td>
                            <td class="small text-break">{{ $notification->recipient->name }}<div class="text-muted">{{ $notification->recipient->email }}</div></td>
                            <td class="small text-nowrap">
                                {{ $notification->type === App\Enums\NotificationType::Escalation ? 'Overdue' : $notification->lead_day.' days' }}
                            </td>
                            <td class="small">
                                @if ($notification->delivered)
                                    <span class="badge text-bg-success">Delivered</span>
                                @elseif ($notification->hasFailed())
                                    <span class="badge text-bg-danger">Failed</span>
                                    <div class="text-muted text-break">{{ $notification->failure_reason }}</div>
                                @else
                                    <span class="badge text-bg-secondary">Pending</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No notifications sent yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $notifications->links() }}
    </div>
@endsection
