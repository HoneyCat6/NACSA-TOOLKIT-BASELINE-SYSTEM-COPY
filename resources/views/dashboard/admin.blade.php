@extends('layouts.app')

@section('title', 'Admin dashboard')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0">Admin dashboard</h1>
        <a href="{{ route('admin.users.create') }}" class="btn btn-primary">Add user</a>
    </div>

    @unless ($hasActiveOfficer)
        <div class="alert alert-info d-flex flex-wrap justify-content-between align-items-center gap-2" role="alert">
            <span><strong>Getting started:</strong> no Compliance Officer account is active yet. Officers upload the self-assessments that every dashboard reports on.</span>
            <a href="{{ route('admin.users.create') }}" class="btn btn-sm btn-primary">Add a Compliance Officer</a>
        </div>
    @endunless

    <h2 class="h5">Registered users</h2>
    <div class="row g-3 mb-4">
        @foreach ($roleCounts as $count)
            <div class="col-md-4">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="small text-muted mb-1">{{ $count['role']->label() }}</div>
                        <div class="fs-4 fw-semibold">{{ $count['active'] }} <span class="fs-6 fw-normal text-muted">active</span></div>
                        <div class="small text-muted">{{ $count['inactive'] }} deactivated</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <h2 class="h5">System health</h2>
    <div class="row g-3 mb-4">
        <div class="col-md-6 col-xl">
            <div class="card shadow-sm h-100 @unless ($schedulerRunning) border-danger @endunless">
                <div class="card-body">
                    <div class="small text-muted mb-1">Scheduler</div>
                    <div class="fs-4 fw-semibold @unless ($schedulerRunning) text-danger @endunless">{{ $schedulerRunning ? 'Running' : 'Not running' }}</div>
                    <div class="small text-muted">
                        @if ($lastSchedulerRun)
                            Last run {{ $lastSchedulerRun->format('j M Y, H:i') }} ({{ $lastSchedulerRun->diffForHumans() }})
                        @else
                            No run recorded. Start <code>php artisan schedule:work</code>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="small text-muted mb-1">Queued jobs</div>
                    <div class="fs-4 fw-semibold">{{ $queuedJobs }}</div>
                    <div class="small text-muted">Emails waiting for the queue worker</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl">
            <div class="card shadow-sm h-100 @if ($failedJobs > 0) border-danger @endif">
                <div class="card-body">
                    <div class="small text-muted mb-1">Failed jobs</div>
                    <div class="fs-4 fw-semibold @if ($failedJobs > 0) text-danger @endif">{{ $failedJobs }}</div>
                    <div class="small text-muted">Jobs that gave up after retrying</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="small text-muted mb-1">Failed sign-ins · last 24 h</div>
                    <div class="fs-4 fw-semibold">{{ $failedLoginsToday }}</div>
                    <div class="small text-muted">Wrong password, deactivated account or OTP lockout</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="small text-muted mb-1">Mail driver</div>
                    <div class="fs-4 fw-semibold">{{ $mailer }}</div>
                    <div class="small text-muted">Configured in <code>.env</code></div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
        <h2 class="h5 mb-0">Recent technical activity</h2>
        <a href="{{ route('admin.audit-logs.index') }}" class="btn btn-sm btn-outline-secondary">Full audit log</a>
    </div>
    @include('admin.audit-logs.partials.table', ['logs' => $recentLogs])
@endsection
