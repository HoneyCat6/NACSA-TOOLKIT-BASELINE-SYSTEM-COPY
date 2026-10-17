<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\ComplianceDeadline;
use App\Models\User;

/**
 * PRD §4.2: Compliance Officers configure deadlines and see the notification history. Every officer manages every
 * deadline (DECISIONS D-47).
 */
class ComplianceDeadlinePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::ComplianceOfficer);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::ComplianceOfficer);
    }

    public function update(User $user, ComplianceDeadline $deadline): bool
    {
        return $user->hasRole(Role::ComplianceOfficer);
    }

    public function acknowledge(User $user, ComplianceDeadline $deadline): bool
    {
        return $user->hasRole(Role::ComplianceOfficer);
    }

    public function markMet(User $user, ComplianceDeadline $deadline): bool
    {
        return $user->hasRole(Role::ComplianceOfficer);
    }

    /**
     * D-47: once a reminder or escalation has gone out, the deadline is kept so FR-45's history stays complete.
     */
    public function delete(User $user, ComplianceDeadline $deadline): bool
    {
        $hasNotifications = $deadline->relationLoaded('notifications')
            ? $deadline->notifications->isNotEmpty()
            : $deadline->notifications()->exists();

        return $user->hasRole(Role::ComplianceOfficer) && ! $hasNotifications;
    }
}
