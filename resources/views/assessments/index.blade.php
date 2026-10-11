@extends('layouts.app')

@section('title', 'Assessment history')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0">Assessment history</h1>
        @can('create', App\Models\Assessment::class)
            <a href="{{ route('assessments.upload') }}" class="btn btn-primary">Upload assessment</a>
        @endcan
    </div>

    @if ($assessments->isEmpty())
        <div class="card shadow-sm">
            <div class="card-body text-center py-5">
                <p class="lead mb-1">No assessment cycles yet</p>
                <p class="text-muted mb-0">Each uploaded NCSB v1.4 toolkit becomes a cycle in this history.</p>
            </div>
        </div>
    @else
        @include('assessments.partials.history-table', ['assessments' => $assessments])
    @endif
@endsection
