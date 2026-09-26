@extends('layouts.guest')

@section('title', 'Forgot password')

@section('content')
    <h2 class="h4 mb-1">Reset your password</h2>
    <p class="text-muted small mb-4">Enter your email and we will send you a reset link.</p>

    <form method="POST" action="{{ route('password.email') }}" novalidate>
        @csrf
        <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                   class="form-control @error('email') is-invalid @enderror">
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="d-flex justify-content-between align-items-center">
            <a href="{{ route('login') }}" class="small">Back to sign in</a>
            <button type="submit" class="btn btn-primary">Send reset link</button>
        </div>
    </form>
@endsection
