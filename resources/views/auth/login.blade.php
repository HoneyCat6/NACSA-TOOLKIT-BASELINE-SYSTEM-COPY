@extends('layouts.guest')

@section('title', 'Sign in')

@section('content')
    <h2 class="h4 mb-1">Sign in</h2>
    <p class="text-muted small mb-4">Access is determined by your assigned role.</p>

    <form method="POST" action="{{ route('login') }}" novalidate>
        @csrf
        <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                   class="form-control @error('email') is-invalid @enderror">
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <input id="password" type="password" name="password" required autocomplete="current-password"
                   class="form-control @error('password') is-invalid @enderror">
            @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="d-flex justify-content-between align-items-center">
            <a href="{{ route('password.request') }}" class="small">Forgot password?</a>
            <button type="submit" class="btn btn-primary">Continue</button>
        </div>
    </form>
@endsection
