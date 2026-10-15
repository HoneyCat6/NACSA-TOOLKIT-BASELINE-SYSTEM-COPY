<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\ExecutiveReport;
use App\Models\User;

/**
 * PRD §4.2: officers generate executive summaries; officers and executives download them.
 */
class ExecutiveReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::ComplianceOfficer, Role::ExecutiveReviewer);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::ComplianceOfficer);
    }

    public function download(User $user, ExecutiveReport $report): bool
    {
        return $this->viewAny($user);
    }
}
