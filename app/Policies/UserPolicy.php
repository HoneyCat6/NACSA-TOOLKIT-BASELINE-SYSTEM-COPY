<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

/**
 * PRD §4.2 / FR-07: only the Admin manages accounts (DECISIONS D-53).
 *
 * Admins cannot deactivate themselves or change their own role, so at least one active Admin always remains.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::Admin);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::Admin);
    }

    public function update(User $user, User $model): bool
    {
        return $user->hasRole(Role::Admin);
    }

    public function changeRole(User $user, User $model): bool
    {
        return $user->hasRole(Role::Admin) && $user->isNot($model);
    }

    public function deactivate(User $user, User $model): bool
    {
        return $user->hasRole(Role::Admin) && $user->isNot($model) && $model->is_active;
    }

    public function reactivate(User $user, User $model): bool
    {
        return $user->hasRole(Role::Admin) && ! $model->is_active;
    }

    public function sendPasswordReset(User $user, User $model): bool
    {
        return $user->hasRole(Role::Admin) && $model->is_active;
    }
}
