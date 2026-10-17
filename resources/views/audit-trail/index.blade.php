@extends('layouts.app')

@section('title', 'Compliance audit trail')

@section('content')
    <h1 class="h3 mb-1">Compliance audit trail</h1>
    <p class="text-muted small mb-3">Assessment uploads, rejections and deletions, executive reports, alert acknowledgements and deadline changes.</p>

    @include('admin.audit-logs.partials.filters', ['formAction' => route('audit-trail.index')])

    @include('admin.audit-logs.partials.table', ['logs' => $logs])

    <div class="mt-3">
        {{ $logs->links() }}
    </div>
@endsection
