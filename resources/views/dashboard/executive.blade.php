@extends('layouts.app')

@section('title', 'Executive dashboard')

@section('content')
    <h1 class="h3 mb-3">Executive dashboard</h1>

    @if ($latest)
        @include('dashboard.partials.latest-assessment')
    @else
        <div class="card shadow-sm">
            <div class="card-body text-center py-5">
                <p class="lead mb-1">No assessment results yet</p>
                <p class="text-muted mb-0">Your compliance posture will appear once the first assessment cycle has been uploaded.</p>
            </div>
        </div>
    @endif
@endsection
