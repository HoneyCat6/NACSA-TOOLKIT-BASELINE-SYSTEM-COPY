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
                @can('viewAny', App\Models\Assessment::class)
                    <li class="nav-item">
                        <a class="nav-link @if (request()->routeIs('assessments.index', 'assessments.show')) active @endif"
                           href="{{ route('assessments.index') }}">Assessments</a>
                    </li>
                @endcan
                @can('viewTrends', App\Models\Assessment::class)
                    <li class="nav-item">
                        <a class="nav-link @if (request()->routeIs('trends.index')) active @endif"
                           href="{{ route('trends.index') }}">Trends</a>
                    </li>
                @endcan
                @can('viewHeatmap', App\Models\Assessment::class)
                    <li class="nav-item">
                        <a class="nav-link @if (request()->routeIs('heatmap.index')) active @endif"
                           href="{{ route('heatmap.index') }}">Heatmap</a>
                    </li>
                @endcan
                @can('viewAny', App\Models\ExecutiveReport::class)
                    <li class="nav-item">
                        <a class="nav-link @if (request()->routeIs('reports.*')) active @endif"
                           href="{{ route('reports.index') }}">Reports</a>
                    </li>
                @endcan
                @can('viewAny', App\Models\ComplianceDeadline::class)
                    <li class="nav-item">
                        <a class="nav-link @if (request()->routeIs('deadlines.*', 'notifications.*')) active @endif"
                           href="{{ route('deadlines.index') }}">Deadlines</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link @if (request()->routeIs('audit-trail.*')) active @endif"
                           href="{{ route('audit-trail.index') }}">Audit trail</a>
                    </li>
                @endcan
                @if (auth()->user()->hasRole(App\Enums\Role::Admin))
                    <li class="nav-item">
                        <a class="nav-link @if (request()->routeIs('admin.users.*')) active @endif"
                           href="{{ route('admin.users.index') }}">Users</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link @if (request()->routeIs('admin.audit-logs.*')) active @endif"
                           href="{{ route('admin.audit-logs.index') }}">Audit log</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link @if (request()->routeIs('admin.settings.*')) active @endif"
                           href="{{ route('admin.settings.edit') }}">Settings</a>
                    </li>
                @endif
                @can('create', App\Models\Assessment::class)
                    <li class="nav-item">
                        <a class="nav-link @if (request()->routeIs('assessments.upload')) active @endif"
                           href="{{ route('assessments.upload') }}">Upload</a>
                    </li>
                @endcan
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
