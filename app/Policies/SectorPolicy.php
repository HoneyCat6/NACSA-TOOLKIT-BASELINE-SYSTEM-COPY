<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

class SectorPolicy
{
    public function manage(User $user): bool
    {
        return $user->hasRole(Role::Admin);
    }
}
