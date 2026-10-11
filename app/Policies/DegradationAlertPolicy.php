<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\DegradationAlert;
use App\Models\User;

/**
 * PRD §4.2: only Compliance Officers acknowledge degradation alerts (FR-20).
 */
class DegradationAlertPolicy
{
    public function acknowledge(User $user, DegradationAlert $alert): bool
    {
        return $user->hasRole(Role::ComplianceOfficer);
    }

    public function acknowledgeAll(User $user): bool
    {
        return $user->hasRole(Role::ComplianceOfficer);
    }
}
