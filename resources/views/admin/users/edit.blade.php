@extends('layouts.app')

@section('title', 'Edit '.$user->name)

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h1 class="h4 mb-4">Edit {{ $user->name }}</h1>
                    <form method="POST" action="{{ route('admin.users.update', $user) }}" novalidate>
                        @csrf
                        @method('PATCH')
                        @include('admin.users.partials.form-fields', ['user' => $user])
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Save changes</button>
                            <a href="{{ route('admin.users.index') }}" class="btn btn-link">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
