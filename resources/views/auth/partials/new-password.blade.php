<div class="mb-3">
    <label for="password" class="form-label">New password</label>
    <input id="password" type="password" name="password" required autocomplete="new-password"
           aria-describedby="passwordHelp" class="form-control @error('password') is-invalid @enderror">
    @error('password')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
    <div id="passwordHelp" class="form-text">
        At least 12 characters, with upper- and lower-case letters, a number and a symbol.
    </div>
</div>
<div class="mb-4">
    <label for="password_confirmation" class="form-label">Confirm new password</label>
    <input id="password_confirmation" type="password" name="password_confirmation" required
           autocomplete="new-password" class="form-control">
</div>
