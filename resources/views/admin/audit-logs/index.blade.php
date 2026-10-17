@extends('layouts.app')

@section('title', 'Technical audit log')

@section('content')
    <h1 class="h3 mb-1">Technical audit log</h1>
    <p class="text-muted small mb-3">Sign-ins, OTP and password events, denied access, and account and settings changes.</p>

    @include('admin.audit-logs.partials.filters', ['formAction' => route('admin.audit-logs.index')])

    @include('admin.audit-logs.partials.table', ['logs' => $logs])

    <div class="mt-3">
        {{ $logs->links() }}
    </div>
@endsection
