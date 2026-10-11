@extends('layouts.app')

@section('title', 'Add user')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h1 class="h4 mb-1">Add user</h1>
                    <p class="small text-muted mb-4">The user receives an email link to set their own password, then signs in with an email OTP.</p>
                    <form method="POST" action="{{ route('admin.users.store') }}" novalidate>
                        @csrf
                        @include('admin.users.partials.form-fields', ['user' => null])
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Add user</button>
                            <a href="{{ route('admin.users.index') }}" class="btn btn-link">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
