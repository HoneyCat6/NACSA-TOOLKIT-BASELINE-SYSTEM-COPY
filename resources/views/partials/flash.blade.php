@if (session('status'))
    <div class="alert alert-info" role="status">{{ session('status') }}</div>
@endif
@if (session('warning'))
    <div class="alert alert-warning" role="alert">{{ session('warning') }}</div>
@endif
