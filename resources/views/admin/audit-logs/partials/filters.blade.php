<form method="GET" action="{{ $formAction }}" class="card shadow-sm mb-3">
    <div class="card-body row g-2 align-items-end">
        <div class="col-md-3">
            <label for="action" class="form-label small fw-semibold">Action</label>
            <select id="action" name="action" class="form-select form-select-sm">
                <option value="">All actions</option>
                @foreach ($actions as $auditAction)
                    <option value="{{ $auditAction }}" @selected(($filters['action'] ?? null) === $auditAction)>{{ $auditAction }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label for="user" class="form-label small fw-semibold">User name or email</label>
            <input id="user" type="search" name="user" value="{{ $filters['user'] ?? '' }}" class="form-control form-control-sm">
        </div>
        <div class="col-6 col-md-2">
            <label for="from" class="form-label small fw-semibold">From</label>
            <input id="from" type="date" name="from" value="{{ $filters['from'] ?? '' }}"
                   class="form-control form-control-sm @error('from') is-invalid @enderror">
        </div>
        <div class="col-6 col-md-2">
            <label for="to" class="form-label small fw-semibold">To</label>
            <input id="to" type="date" name="to" value="{{ $filters['to'] ?? '' }}"
                   class="form-control form-control-sm @error('to') is-invalid @enderror">
            @error('to')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-sm btn-primary">Filter</button>
            <a href="{{ $formAction }}" class="btn btn-sm btn-link">Clear</a>
        </div>
    </div>
</form>
