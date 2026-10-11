<div class="mb-3">
    <label for="name" class="form-label">Name</label>
    <input id="name" type="text" name="name" value="{{ old('name', $user?->name) }}" required maxlength="255"
           class="form-control @error('name') is-invalid @enderror">
    @error('name')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
<div class="mb-3">
    <label for="email" class="form-label">Email</label>
    <input id="email" type="email" name="email" value="{{ old('email', $user?->email) }}" required maxlength="255"
           class="form-control @error('email') is-invalid @enderror">
    @error('email')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
<div class="mb-3">
    <label for="role" class="form-label">Role</label>
    @php($roleLocked = $user !== null && auth()->user()->cannot('changeRole', $user))
    <select id="role" name="role" required @class(['form-select', 'is-invalid' => $errors->has('role')]) @disabled($roleLocked)>
        @foreach ($roles as $role)
            <option value="{{ $role->value }}" @selected(old('role', $user?->role?->value) === $role->value)>{{ $role->label() }}</option>
        @endforeach
    </select>
    @if ($roleLocked)
        <input type="hidden" name="role" value="{{ $user->role->value }}">
        <div class="form-text">You cannot change your own role.</div>
    @endif
    @error('role')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
