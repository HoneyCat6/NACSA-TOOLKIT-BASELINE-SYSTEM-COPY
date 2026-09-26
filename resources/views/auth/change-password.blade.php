@extends('layouts.app')

@section('title', 'Change password')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h1 class="h4 mb-4">Change password</h1>
                    <form method="POST" action="{{ route('password.change.update') }}" novalidate>
                        @csrf
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current password</label>
                            <input id="current_password" type="password" name="current_password" required
                                   autocomplete="current-password"
                                   class="form-control @error('current_password') is-invalid @enderror">
                            @error('current_password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        @include('auth.partials.new-password')
                        <button type="submit" class="btn btn-primary w-100">Update password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
