@extends('layouts.app')

@section('title', 'Users')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0">Users</h1>
        <a href="{{ route('admin.users.create') }}" class="btn btn-primary">Add user</a>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col">Email</th>
                        <th scope="col">Role</th>
                        <th scope="col">Status</th>
                        <th scope="col">Password changed</th>
                        <th scope="col" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $user)
                        <tr @class(['text-muted' => ! $user->is_active])>
                            <td class="fw-semibold">
                                {{ $user->name }}
                                @if ($user->is(auth()->user()))
                                    <span class="badge text-bg-light border ms-1">You</span>
                                @endif
                            </td>
                            <td class="text-break">{{ $user->email }}</td>
                            <td><span class="badge text-bg-secondary">{{ $user->role->label() }}</span></td>
                            <td>
                                @if ($user->is_active)
                                    <span class="badge text-bg-success">Active</span>
                                @else
                                    <span class="badge text-bg-dark">Deactivated</span>
                                @endif
                            </td>
                            <td class="small text-nowrap">
                                {{ $user->password_changed_at?->format('j M Y') ?? 'Not set yet' }}
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex flex-wrap justify-content-end gap-1">
                                    <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                                    @can('sendPasswordReset', $user)
                                        <form method="POST" action="{{ route('admin.users.password-reset', $user) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-secondary">Send password link</button>
                                        </form>
                                    @endcan
                                    @can('deactivate', $user)
                                        <form method="POST" action="{{ route('admin.users.deactivate', $user) }}" x-data
                                              @submit="if (! confirm({{ Js::from("Deactivate {$user->name}? They will be signed out and unable to sign in.") }})) $event.preventDefault()">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Deactivate</button>
                                        </form>
                                    @endcan
                                    @can('reactivate', $user)
                                        <form method="POST" action="{{ route('admin.users.reactivate', $user) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-success">Reactivate</button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <p class="small text-muted mt-2 mb-0">Accounts are deactivated rather than deleted, so assessment and audit history keeps its owner.</p>
@endsection
