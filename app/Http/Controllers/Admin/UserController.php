<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditCategory;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Admin user management (FR-07, DECISIONS D-53). Accounts are deactivated, never deleted.
 */
class UserController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function index(): View
    {
        Gate::authorize('viewAny', User::class);

        return view('admin.users.index', [
            'users' => User::query()
                ->orderByDesc('is_active')
                ->orderBy('role')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', User::class);

        return view('admin.users.create', ['roles' => Role::cases()]);
    }

    /**
     * The account starts with an unusable random password and an emailed link to set the real one.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = User::query()->create([
            ...$request->safe()->only(['name', 'email', 'role']),
            'password' => Str::password(64),
            'is_active' => true,
            'password_changed_at' => null,
        ]);

        $this->audit->log('user.created', AuditCategory::Technical, $user, [
            'email' => $user->email,
            'role' => $user->role->value,
        ]);

        $status = Password::sendResetLink(['email' => $user->email]);

        return redirect()
            ->route('admin.users.index')
            ->with('status', $status === Password::RESET_LINK_SENT
                ? "{$user->name} was added. A link to set their password was emailed to {$user->email}."
                : "{$user->name} was added, but the password link could not be sent. Use \"Send password link\" to try again.");
    }

    public function edit(User $user): View
    {
        Gate::authorize('update', $user);

        return view('admin.users.edit', [
            'user' => $user,
            'roles' => Role::cases(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $user->fill($request->safe()->only(['name', 'email', 'role']));
        $changes = collect($user->getDirty())
            ->mapWithKeys(fn (mixed $value, string $field): array => [$field => [
                'from' => $this->auditValue($user->getOriginal($field)),
                'to' => $this->auditValue($user->{$field}),
            ]])
            ->all();

        if ($changes === []) {
            return redirect()->route('admin.users.index')->with('status', "No changes to {$user->name}.");
        }

        $user->save();

        $this->audit->log('user.updated', AuditCategory::Technical, $user, ['changes' => $changes]);

        return redirect()->route('admin.users.index')->with('status', "{$user->name} was updated.");
    }

    /**
     * FR-07: soft-deactivate instead of delete. The user's open session ends on their next request.
     */
    public function deactivate(User $user): RedirectResponse
    {
        Gate::authorize('deactivate', $user);

        $user->update(['is_active' => false]);
        $this->audit->log('user.deactivated', AuditCategory::Technical, $user, ['email' => $user->email]);

        return back()->with('status', "{$user->name} was deactivated and can no longer sign in.");
    }

    public function reactivate(User $user): RedirectResponse
    {
        Gate::authorize('reactivate', $user);

        $user->update(['is_active' => true]);
        $this->audit->log('user.reactivated', AuditCategory::Technical, $user, ['email' => $user->email]);

        return back()->with('status', "{$user->name} was reactivated.");
    }

    public function sendPasswordReset(User $user): RedirectResponse
    {
        Gate::authorize('sendPasswordReset', $user);

        $status = Password::sendResetLink(['email' => $user->email]);

        if ($status !== Password::RESET_LINK_SENT) {
            return back()->with('warning', __($status));
        }

        $this->audit->log('user.password_link_sent', AuditCategory::Technical, $user, ['email' => $user->email]);

        return back()->with('status', "A password link was emailed to {$user->email}.");
    }

    private function auditValue(mixed $value): mixed
    {
        return $value instanceof Role ? $value->value : $value;
    }
}
