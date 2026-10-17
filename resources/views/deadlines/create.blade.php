@extends('layouts.app')

@section('title', 'Add deadline')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-10 col-lg-8 col-xl-6">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h1 class="h4 mb-1">Add deadline</h1>
                    <p class="small text-muted mb-4">Reminders go out by email from the daily sweep at 08:00.</p>
                    <form method="POST" action="{{ route('deadlines.store') }}" novalidate>
                        @csrf
                        @include('deadlines.partials.form-fields')
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Add deadline</button>
                            <a href="{{ route('deadlines.index') }}" class="btn btn-link">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
