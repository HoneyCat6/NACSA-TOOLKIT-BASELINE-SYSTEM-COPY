@extends('layouts.guest')

@section('title', 'Acknowledge deadline')

@section('content')
    <h2 class="h4 mb-1">{{ $deadline->title }}</h2>
    <p class="text-muted small mb-4">Due {{ $deadline->due_date->format('l, j M Y') }}</p>

    @if ($problem)
        <div class="alert alert-warning" role="alert">{{ $problem }}</div>
        <a href="{{ route('login') }}" class="btn btn-outline-primary">Sign in</a>
    @elseif ($deadline->isMet())
        <div class="alert alert-success" role="status">This deadline is already met. There is nothing to acknowledge.</div>
    @elseif ($deadline->isAcknowledged())
        <div class="alert alert-success" role="status">
            Acknowledged on {{ $deadline->acknowledged_at->format('j M Y, H:i') }}{{ $deadline->acknowledgedBy ? ' by '.$deadline->acknowledgedBy->name : '' }}.
            It will not be escalated. Reminders continue until the assessment is uploaded.
        </div>
    @else
        <p>
            Acknowledge as <strong>{{ $user->name }}</strong> that you are working on this deadline.
            It will then not be escalated when the due date passes. Reminders continue until the assessment is uploaded.
        </p>
        <form method="POST" action="{{ request()->fullUrl() }}">
            @csrf
            <button type="submit" class="btn btn-primary">Acknowledge deadline</button>
        </form>
    @endif
@endsection
