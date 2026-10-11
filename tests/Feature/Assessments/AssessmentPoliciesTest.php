<?php

namespace Tests\Feature\Assessments;

use App\Enums\Role;
use App\Models\Assessment;
use App\Models\DegradationAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * PRD §4.2 permission matrix for Sprint 1 abilities (SEC-03).
 */
class AssessmentPoliciesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{Role, bool, bool, bool, bool}>
     */
    public static function matrix(): array
    {
        // role => [view history & results, upload, acknowledge alerts, view trends]
        return [
            'admin' => [Role::Admin, false, false, false, false],
            'compliance officer' => [Role::ComplianceOfficer, true, true, true, true],
            'executive reviewer' => [Role::ExecutiveReviewer, false, false, false, true],
        ];
    }

    #[DataProvider('matrix')]
    public function test_role_permissions(Role $role, bool $canView, bool $canUpload, bool $canAcknowledge, bool $canViewTrends): void
    {
        $user = User::factory()->create(['role' => $role]);
        $assessment = Assessment::factory()->create();
        $alert = DegradationAlert::factory()->create();

        $this->assertSame($canView, $user->can('viewAny', Assessment::class));
        $this->assertSame($canView, $user->can('view', $assessment));
        $this->assertSame($canUpload, $user->can('create', Assessment::class));
        $this->assertSame($canAcknowledge, $user->can('acknowledge', $alert));
        $this->assertSame($canAcknowledge, $user->can('acknowledgeAll', DegradationAlert::class));
        $this->assertSame($canViewTrends, $user->can('viewTrends', Assessment::class));
    }

    #[DataProvider('matrix')]
    public function test_only_officers_may_delete_and_only_the_latest_cycle(Role $role, bool $canView, bool $canUpload): void
    {
        $user = User::factory()->create(['role' => $role]);
        $earlier = Assessment::factory()->create(['assessment_date' => '2025-06-01']);
        $latest = Assessment::factory()->create(['assessment_date' => '2026-01-01']);

        $this->assertSame($canUpload, $user->can('delete', $latest));
        $this->assertFalse($user->can('delete', $earlier));
    }
}
