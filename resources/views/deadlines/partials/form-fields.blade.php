<div class="mb-3">
    <label for="title" class="form-label">Title</label>
    <input id="title" type="text" name="title" value="{{ old('title', $deadline->title) }}" required maxlength="255"
           placeholder="e.g. 2026 H2 NCSB self-assessment" class="form-control @error('title') is-invalid @enderror">
    @error('title')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
<div class="mb-3">
    <label for="description" class="form-label">Description <span class="text-muted small">(optional)</span></label>
    <textarea id="description" name="description" rows="2" maxlength="2000"
              class="form-control @error('description') is-invalid @enderror">{{ old('description', $deadline->description) }}</textarea>
    @error('description')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
<div class="row g-3 mb-3">
    <div class="col-sm-6">
        <label for="due_date" class="form-label">Due date</label>
        <input id="due_date" type="date" name="due_date" value="{{ old('due_date', $deadline->due_date?->toDateString()) }}" required
               class="form-control @error('due_date') is-invalid @enderror">
        @error('due_date')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-sm-6">
        <label for="lead_days" class="form-label">Remind this many days before</label>
        @php($leadDays = old('lead_days', $deadline->leadDays()))
        <input id="lead_days" type="text" name="lead_days" inputmode="numeric" required
               value="{{ is_array($leadDays) ? implode(', ', $leadDays) : $leadDays }}"
               @class(['form-control', 'is-invalid' => $errors->has('lead_days') || $errors->has('lead_days.*')])>
        <div class="form-text">Comma-separated, e.g. 30, 14, 7.</div>
        @error('lead_days')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        @error('lead_days.*')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>
<div class="row g-3 mb-4">
    <div class="col-sm-6">
        <label for="officer_id" class="form-label">Primary contact</label>
        <select id="officer_id" name="officer_id" required class="form-select @error('officer_id') is-invalid @enderror">
            @foreach ($officers as $officer)
                <option value="{{ $officer->id }}" @selected((int) old('officer_id', $deadline->officer_id) === $officer->id)>{{ $officer->name }}</option>
            @endforeach
        </select>
        <div class="form-text">Receives the reminders.</div>
        @error('officer_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-sm-6">
        <label for="secondary_contact_id" class="form-label">Escalation contact</label>
        <select id="secondary_contact_id" name="secondary_contact_id" required class="form-select @error('secondary_contact_id') is-invalid @enderror">
            <option value="">Choose…</option>
            @foreach ($escalationContacts as $contact)
                <option value="{{ $contact->id }}" @selected((int) old('secondary_contact_id', $deadline->secondary_contact_id) === $contact->id)>
                    {{ $contact->name }} ({{ $contact->role->label() }})
                </option>
            @endforeach
        </select>
        <div class="form-text">Alerted if the due date passes unmet and unacknowledged.</div>
        @error('secondary_contact_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>
