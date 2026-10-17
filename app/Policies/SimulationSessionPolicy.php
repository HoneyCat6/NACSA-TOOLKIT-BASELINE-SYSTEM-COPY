<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\SimulationSession;
use App\Models\User;

/**
 * PRD §4.2: officers and executives use the simulator; saved scenarios are private to their author (DECISIONS D-25, D-26).
 */
class SimulationSessionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::ComplianceOfficer, Role::ExecutiveReviewer);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function view(User $user, SimulationSession $session): bool
    {
        return $this->viewAny($user) && $session->creator()->is($user);
    }

    public function delete(User $user, SimulationSession $session): bool
    {
        return $this->view($user, $session);
    }
}
