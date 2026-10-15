<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Assessment;
use App\Models\User;

/**
 * PRD §4.2: officers upload, delete and open cycle results; executives see the trend dashboard only (DECISIONS D-37).
 */
class AssessmentPolicy
{
    /**
     * D-37: assessment history and per-element results are for Compliance Officers only.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::ComplianceOfficer);
    }

    public function view(User $user, Assessment $assessment): bool
    {
        return $this->viewAny($user);
    }

    public function viewTrends(User $user): bool
    {
        return $user->hasRole(Role::ComplianceOfficer, Role::ExecutiveReviewer);
    }

    /**
     * FR-50 – FR-54: the control effectiveness heatmap is for officers and executives.
     */
    public function viewHeatmap(User $user): bool
    {
        return $user->hasRole(Role::ComplianceOfficer, Role::ExecutiveReviewer);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::ComplianceOfficer);
    }

    /**
     * DECISIONS D-35: officers may undo the latest cycle only.
     */
    public function delete(User $user, Assessment $assessment): bool
    {
        return $user->hasRole(Role::ComplianceOfficer) && $assessment->isLatest();
    }
}
