<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Sign in') · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-light">
    <div class="container-fluid min-vh-100">
        <div class="row min-vh-100">
            <div class="col-lg-5 d-none d-lg-flex flex-column justify-content-center bg-dark text-white p-5">
                <p class="text-uppercase small text-white-50 mb-2">National Cyber Security Baseline v1.4</p>
                <h1 class="display-6 fw-semibold">NACSA Toolkit Baseline System</h1>
                <p class="lead text-white-50 mt-3">
                    Continuous maturity monitoring, executive reporting and compliance tracking
                    for the NCSB self-assessment.
                </p>
            </div>
            <div class="col-lg-7 d-flex align-items-center justify-content-center p-4">
                <div class="w-100" style="max-width: 420px;">
                    <h1 class="h4 d-lg-none mb-4">NACSA Toolkit Baseline System</h1>
                    @include('partials.flash')
                    @yield('content')
                </div>
            </div>
        </div>
    </div>
</body>
</html>
