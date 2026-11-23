@extends('layouts.app')

@section('title', 'Sector constructs')

@section('content')
    <h1 class="h3 mb-3">Sector constructs</h1>

    @if ($errors->any())
        <ul class="alert alert-danger list-unstyled" role="alert">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <div class="row g-4">
        <aside class="col-lg-3">
            @if ($sectors->isNotEmpty())
                <nav class="list-group mb-3" aria-label="Sectors">
                    @foreach ($sectors as $option)
                        <a class="list-group-item list-group-item-action @if ($sector?->is($option)) active @endif"
                           @if ($sector?->is($option)) aria-current="page" @endif
                           href="{{ route('admin.constructs.index', $option) }}">{{ $option->name }}</a>
                    @endforeach
                </nav>
            @endif

            <form method="POST" action="{{ route('admin.sectors.store') }}" class="card card-body shadow-sm">
                @csrf
                <h2 class="h6">Add a sector</h2>
                <label for="sector-name" class="form-label small">Sector name</label>
                <input id="sector-name" name="name" class="form-control mb-2" maxlength="150" required>
                <label for="sector-cop" class="form-label small">Code of Practice reference</label>
                <input id="sector-cop" name="code_of_practice" class="form-control mb-3" maxlength="255">
                <button class="btn btn-outline-primary btn-sm align-self-start">Add sector</button>
            </form>
        </aside>

        @if ($sector)
            <div class="col-lg-9">
                <h2 class="h4 mb-1">{{ $sector->name }}</h2>
                @if ($sector->code_of_practice)
                    <p class="text-muted small mb-1">{{ $sector->code_of_practice }}</p>
                @endif
                <p class="text-muted small mb-3">Changes take effect on the next upload for this sector.</p>

                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h3 class="h6 mb-0">Core NCSB v1.4 constructs</h3>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Construct</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Change applicability</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($core as $element)
                                    @php($override = $overrides->get($element->id))
                                    @php($disabled = $override?->status === App\Models\SectorCoreConstruct::Disabled)
                                    <tr>
                                        <th scope="row" class="fw-normal">
                                            <strong>{{ $element->code }}</strong> {{ $element->name }}
                                        </th>
                                        <td>
                                            <span class="badge {{ $disabled ? 'text-bg-secondary' : 'text-bg-success' }}">
                                                {{ $disabled ? 'Disabled (N/A)' : 'Core active' }}
                                            </span>
                                            @if ($override?->reason !== null)
                                                <div class="small text-muted text-break">{{ $override->reason }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            <details>
                                                <summary class="small">{{ $disabled ? 'Restore' : 'Drop for this sector' }}</summary>
                                                <form method="POST"
                                                      action="{{ route('admin.constructs.core.update', [$sector, $element]) }}"
                                                      class="mt-2 mb-1">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="status" value="{{ $disabled ? 'active' : 'disabled' }}">
                                                    <label for="reason-{{ $element->id }}" class="form-label small">Reason for {{ $element->code }} (optional)</label>
                                                    <input id="reason-{{ $element->id }}" name="reason" class="form-control form-control-sm mb-2" maxlength="500">
                                                    <button class="btn btn-sm {{ $disabled ? 'btn-outline-primary' : 'btn-outline-danger' }}">
                                                        {{ $disabled ? 'Restore construct' : 'Mark as N/A' }}
                                                    </button>
                                                </form>
                                            </details>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-body">
                        <h3 class="h6">Sector-specific constructs</h3>
                        @if ($constructs->isNotEmpty())
                            <ul class="list-group list-group-flush mb-3">
                                @foreach ($constructs as $construct)
                                    <li class="list-group-item px-0">
                                        <strong>{{ $construct->code }} · {{ $construct->name }}</strong>
                                        <span class="badge text-bg-primary">{{ $construct->domain->label() }}</span>
                                        <div class="small text-muted text-break">{{ $construct->requirement }}</div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        <form method="POST" action="{{ route('admin.constructs.store', $sector) }}" class="row g-3">
                            @csrf
                            <div class="col-sm-3">
                                <label for="construct-code" class="form-label">Code</label>
                                <input id="construct-code" name="code" value="{{ old('code') }}" placeholder="SC-01"
                                       class="form-control" maxlength="20" pattern="[A-Za-z0-9][A-Za-z0-9._\-]*" required>
                            </div>
                            <div class="col-sm-5">
                                <label for="construct-name" class="form-label">Construct name</label>
                                <input id="construct-name" name="name" class="form-control" maxlength="150" required>
                            </div>
                            <div class="col-sm-4">
                                <label for="construct-domain" class="form-label">Domain</label>
                                <select id="construct-domain" name="domain" class="form-select" required>
                                    <option value="">Select a domain</option>
                                    @foreach (App\Enums\Domain::cases() as $domain)
                                        <option value="{{ $domain->value }}" @selected(old('domain') === $domain->value)>{{ $domain->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <label for="construct-requirement" class="form-label">Code of Practice requirement</label>
                                <textarea id="construct-requirement" name="requirement" class="form-control" rows="3" maxlength="3000" required>{{ old('requirement') }}</textarea>
                            </div>
                            <div class="col-12">
                                <button class="btn btn-primary">Add construct</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection
