<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid px-md-4">
        <a class="navbar-brand fw-semibold" href="{{ route('dashboard') }}">NACSA Toolkit</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
                aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link @if (request()->routeIs('dashboard')) active @endif"
                       href="{{ route('dashboard') }}">Dashboard</a>
                </li>
            </ul>
            <div class="d-flex align-items-center gap-3">
                <span class="navbar-text small">
                    {{ auth()->user()->name }}
                    <span class="badge text-bg-secondary ms-1">{{ auth()->user()->role->label() }}</span>
                </span>
                <a class="btn btn-sm btn-outline-light" href="{{ route('password.change') }}">Change password</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-light">Sign out</button>
                </form>
            </div>
        </div>
    </div>
</nav>
