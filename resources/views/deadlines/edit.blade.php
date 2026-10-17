@extends('layouts.app')

@section('title', 'Edit deadline')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-10 col-lg-8 col-xl-6">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h1 class="h4 mb-1">Edit deadline</h1>
                    <p class="small text-muted mb-4">
                        Changing the due date starts the reminders over for the new date and clears the acknowledgement.
                        Notifications already sent stay in the history.
                    </p>
                    <form method="POST" action="{{ route('deadlines.update', $deadline) }}" novalidate>
                        @csrf
                        @method('PATCH')
                        @include('deadlines.partials.form-fields')
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Save changes</button>
                            <a href="{{ route('deadlines.index') }}" class="btn btn-link">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
