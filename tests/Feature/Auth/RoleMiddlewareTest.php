<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth', 'mfa', 'role:admin'])
            ->get('/_test/admin-only', fn () => 'ok')->name('test.admin-only');
        Route::middleware(['web', 'auth', 'mfa', 'role:compliance_officer,executive_reviewer'])
            ->get('/_test/officer-or-executive', fn () => 'ok');
    }

    /**
     * @return array<string, array{Role, string, int}>
     */
    public static function matrix(): array
    {
        return [
            'admin → admin route' => [Role::Admin, '/_test/admin-only', 200],
            'officer → admin route' => [Role::ComplianceOfficer, '/_test/admin-only', 403],
            'executive → admin route' => [Role::ExecutiveReviewer, '/_test/admin-only', 403],
            'admin → shared route' => [Role::Admin, '/_test/officer-or-executive', 403],
            'officer → shared route' => [Role::ComplianceOfficer, '/_test/officer-or-executive', 200],
            'executive → shared route' => [Role::ExecutiveReviewer, '/_test/officer-or-executive', 200],
        ];
    }

    #[DataProvider('matrix')]
    public function test_role_gate(Role $role, string $uri, int $status): void
    {
        $user = User::factory()->create(['role' => $role]);

        $this->signIn($user)->get($uri)->assertStatus($status);
    }

    public function test_denied_access_is_audited(): void
    {
        $user = User::factory()->executiveReviewer()->create();

        $this->signIn($user)->get('/_test/admin-only')->assertForbidden();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'access.denied',
            'user_id' => $user->id,
            'category' => 'technical',
        ]);
    }

    /**
     * @return array<string, array{Role, string}>
     */
    public static function dashboards(): array
    {
        return [
            'admin' => [Role::Admin, 'Admin dashboard'],
            'officer' => [Role::ComplianceOfficer, 'Compliance dashboard'],
            'executive' => [Role::ExecutiveReviewer, 'Executive dashboard'],
        ];
    }

    #[DataProvider('dashboards')]
    public function test_dashboard_dispatches_by_role(Role $role, string $heading): void
    {
        $user = User::factory()->create(['role' => $role]);

        $this->signIn($user)->get('/dashboard')->assertOk()->assertSee($heading);
    }

    public function test_deactivated_user_is_signed_out_mid_session(): void
    {
        $user = User::factory()->create();
        $this->signIn($user);
        $user->forceFill(['is_active' => false])->save();

        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
