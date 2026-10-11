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
     * @return array<string, array{Role, bool, bool, bool}>
     */
    public static function matrix(): array
    {
        // role => [view assessments & trends, upload, acknowledge alerts]
        return [
            'admin' => [Role::Admin, false, false, false],
            'compliance officer' => [Role::ComplianceOfficer, true, true, true],
            'executive reviewer' => [Role::ExecutiveReviewer, true, false, false],
        ];
    }

    #[DataProvider('matrix')]
    public function test_role_permissions(Role $role, bool $canView, bool $canUpload, bool $canAcknowledge): void
    {
        $user = User::factory()->create(['role' => $role]);
        $assessment = Assessment::factory()->create();
        $alert = DegradationAlert::factory()->create();

        $this->assertSame($canView, $user->can('viewAny', Assessment::class));
        $this->assertSame($canView, $user->can('view', $assessment));
        $this->assertSame($canUpload, $user->can('create', Assessment::class));
        $this->assertSame($canAcknowledge, $user->can('acknowledge', $alert));
    }
}
