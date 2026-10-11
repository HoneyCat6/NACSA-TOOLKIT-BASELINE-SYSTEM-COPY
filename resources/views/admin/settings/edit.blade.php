@extends('layouts.app')

@section('title', 'Settings')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h1 class="h4 mb-1">Settings</h1>
                    <p class="small text-muted mb-4">Shown on the executive summary report. Mail server settings stay in <code>.env</code>.</p>
                    <form method="POST" action="{{ route('admin.settings.update') }}" novalidate>
                        @csrf
                        @method('PUT')
                        <div class="mb-3">
                            <label for="organisation_name" class="form-label">Organisation name</label>
                            <input id="organisation_name" type="text" name="organisation_name" required maxlength="150"
                                   value="{{ old('organisation_name', $organisationName) }}"
                                   class="form-control @error('organisation_name') is-invalid @enderror">
                            <div class="form-text">Printed on the report cover.</div>
                            @error('organisation_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-4">
                            <label for="report_footer" class="form-label">Report footer <span class="text-muted small">(optional)</span></label>
                            <textarea id="report_footer" name="report_footer" rows="3" maxlength="500"
                                      class="form-control @error('report_footer') is-invalid @enderror">{{ old('report_footer', $reportFooter) }}</textarea>
                            <div class="form-text">For example a classification marking such as "CONFIDENTIAL — internal use only".</div>
                            @error('report_footer')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <button type="submit" class="btn btn-primary">Save settings</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
