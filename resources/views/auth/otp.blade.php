@extends('layouts.guest')

@section('title', 'Verify sign-in')

@section('content')
    <h2 class="h4 mb-1">Check your email</h2>
    <p class="text-muted small mb-4">
        We sent a {{ config('security.otp.length') }}-digit code to <strong>{{ $email }}</strong>.
    </p>

    <form method="POST" action="{{ route('otp.verify') }}" novalidate>
        @csrf
        <div class="mb-3">
            <label for="code" class="form-label">Verification code</label>
            <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" required autofocus
                   maxlength="{{ config('security.otp.length') }}"
                   class="form-control form-control-lg text-center font-monospace @error('code') is-invalid @enderror">
            @error('code')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <p class="small text-muted mb-3"
           x-data="{ left: {{ max(0, (int) now()->diffInSeconds($expiresAt, false)) }} }"
           x-init="setInterval(() => left = Math.max(0, left - 1), 1000)">
            <span x-show="left > 0">Code expires in
                <strong x-text="Math.floor(left / 60) + ':' + String(left % 60).padStart(2, '0')"></strong>.</span>
            <span x-show="left === 0" x-cloak>Code expired — request a new one.</span>
            {{ $attemptsRemaining }} attempt(s) remaining.
        </p>
        <button type="submit" class="btn btn-primary w-100">Verify and sign in</button>
    </form>

    <form method="POST" action="{{ route('otp.resend') }}" class="mt-3 text-center">
        @csrf
        <button type="submit" class="btn btn-link btn-sm">Send a new code</button>
    </form>
    <p class="text-center small mt-2"><a href="{{ route('login') }}">Back to sign in</a></p>
@endsection
