@extends('layouts.guest')

@section('title', 'Choose a new password')

@section('content')
    <h2 class="h4 mb-4">Choose a new password</h2>

    <form method="POST" action="{{ route('password.update') }}" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email', $email) }}" required
                   class="form-control @error('email') is-invalid @enderror">
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        @include('auth.partials.new-password')
        <button type="submit" class="btn btn-primary w-100">Reset password</button>
    </form>
@endsection
