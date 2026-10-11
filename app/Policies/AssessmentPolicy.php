<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Assessment;
use App\Models\User;

/**
 * PRD §4.2: officers upload; officers and executives view results.
 */
class AssessmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::ComplianceOfficer, Role::ExecutiveReviewer);
    }

    public function view(User $user, Assessment $assessment): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::ComplianceOfficer);
    }
}
