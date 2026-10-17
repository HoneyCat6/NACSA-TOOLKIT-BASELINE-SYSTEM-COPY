@extends('layouts.app')

@section('title', 'Compliance dashboard')

@section('content')
    <h1 class="h3 mb-3">Compliance dashboard</h1>

    @if ($activeAlertCount > 0)
        <div class="alert alert-danger d-flex flex-wrap justify-content-between align-items-center gap-2" role="alert">
            <span><strong>{{ $activeAlertCount }}</strong> active degradation {{ Str::plural('alert', $activeAlertCount) }} need acknowledgement.</span>
            <a href="{{ route('trends.index') }}" class="btn btn-sm btn-danger">Review alerts</a>
        </div>
    @endif

    @if ($overdueDeadlineCount > 0)
        <div class="alert alert-danger d-flex flex-wrap justify-content-between align-items-center gap-2" role="alert">
            <span><strong>{{ $overdueDeadlineCount }}</strong> compliance {{ Str::plural('deadline', $overdueDeadlineCount) }} {{ $overdueDeadlineCount === 1 ? 'is' : 'are' }} overdue.</span>
            <a href="{{ route('deadlines.index') }}" class="btn btn-sm btn-danger">View deadlines</a>
        </div>
    @endif

    @if ($failedNotificationCount > 0)
        <div class="alert alert-warning d-flex flex-wrap justify-content-between align-items-center gap-2" role="alert">
            <span><strong>{{ $failedNotificationCount }}</strong> deadline {{ Str::plural('email', $failedNotificationCount) }} could not be delivered after retrying.</span>
            <a href="{{ route('notifications.index', ['delivery' => 'failed']) }}" class="btn btn-sm btn-warning">View failures</a>
        </div>
    @endif

    @if ($latest)
        @include('dashboard.partials.latest-assessment')
    @else
        <div class="card shadow-sm">
            <div class="card-body text-center py-5">
                <p class="lead mb-1">No assessments yet</p>
                <p class="text-muted mb-3">Upload your first completed NCSB v1.4 toolkit to see maturity results and trends.</p>
                <a href="{{ route('assessments.upload') }}" class="btn btn-primary">Upload assessment</a>
            </div>
        </div>
    @endif
@endsection
