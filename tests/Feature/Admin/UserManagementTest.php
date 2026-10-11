<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * FR-07, DECISIONS D-53.
 */
class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_every_account_with_role_and_status(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Sys Admin']);
        User::factory()->complianceOfficer()->create(['name' => 'Aina Officer']);
        User::factory()->executiveReviewer()->inactive()->create(['name' => 'Former Exec']);

        $this->signIn($admin)->get(route('admin.users.index'))
            ->assertOk()
            ->assertSeeText('Aina Officer')
            ->assertSeeText('Compliance Officer')
            ->assertSeeText('Former Exec')
            ->assertSeeText('Deactivated')
            ->assertSeeText('Reactivate');
    }

    public function test_admin_creates_a_user_who_is_emailed_a_link_to_set_their_password(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();

        $this->signIn($admin)->post(route('admin.users.store'), [
            'name' => 'New Officer',
            'email' => 'new.officer@example.com',
            'role' => Role::ComplianceOfficer->value,
        ])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('status', 'New Officer was added. A link to set their password was emailed to new.officer@example.com.');

        $user = User::query()->where('email', 'new.officer@example.com')->sole();
        $this->assertSame(Role::ComplianceOfficer, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertNull($user->password_changed_at);
        Notification::assertSentTo($user, ResetPassword::class);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.created',
            'user_id' => $admin->id,
            'auditable_id' => $user->id,
            'category' => 'technical',
        ]);
    }

    public function test_create_validates_unique_email_and_known_role(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['email' => 'taken@example.com']);

        $this->signIn($admin)->post(route('admin.users.store'), [
            'name' => 'Someone',
            'email' => 'taken@example.com',
            'role' => 'superuser',
        ])->assertSessionHasErrors(['email', 'role']);

        $this->assertDatabaseCount('users', 2);
    }

    public function test_admin_changes_a_users_role_and_the_change_is_audited(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->complianceOfficer()->create(['name' => 'Aina', 'email' => 'aina@example.com']);

        $this->signIn($admin)->patch(route('admin.users.update', $user), [
            'name' => 'Aina',
            'email' => 'aina@example.com',
            'role' => Role::ExecutiveReviewer->value,
        ])->assertRedirect(route('admin.users.index'));

        $this->assertSame(Role::ExecutiveReviewer, $user->fresh()->role);
        $metadata = json_decode(DB::table('audit_logs')->where('action', 'user.updated')->value('metadata'), true);
        $this->assertSame(['role' => ['from' => 'compliance_officer', 'to' => 'executive_reviewer']], $metadata['changes']);
    }

    public function test_admin_cannot_change_their_own_role(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Sys Admin', 'email' => 'admin@example.com']);

        $this->signIn($admin)->patch(route('admin.users.update', $admin), [
            'name' => 'Sys Admin',
            'email' => 'admin@example.com',
            'role' => Role::ComplianceOfficer->value,
        ])->assertSessionHasErrors(['role' => 'You cannot change your own role.']);

        $this->assertSame(Role::Admin, $admin->fresh()->role);
    }

    public function test_deactivated_user_is_signed_out_and_cannot_sign_in_and_is_never_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $officer = User::factory()->complianceOfficer()->create();

        $this->signIn($admin)->delete(route('admin.users.deactivate', $officer))
            ->assertSessionHas('status', "{$officer->name} was deactivated and can no longer sign in.");

        $this->assertModelExists($officer);
        $this->assertFalse($officer->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.deactivated', 'auditable_id' => $officer->id]);

        $this->signIn($officer->fresh())->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_admin_cannot_deactivate_themselves(): void
    {
        $admin = User::factory()->admin()->create();

        $this->signIn($admin)->delete(route('admin.users.deactivate', $admin))->assertForbidden();

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_admin_reactivates_a_user(): void
    {
        $admin = User::factory()->admin()->create();
        $officer = User::factory()->complianceOfficer()->inactive()->create();

        $this->signIn($admin)->post(route('admin.users.reactivate', $officer))->assertSessionHasNoErrors();

        $this->assertTrue($officer->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.reactivated', 'auditable_id' => $officer->id]);
    }

    public function test_admin_sends_a_password_link(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $officer = User::factory()->complianceOfficer()->create();

        $this->signIn($admin)->post(route('admin.users.password-reset', $officer))
            ->assertSessionHas('status', "A password link was emailed to {$officer->email}.");

        Notification::assertSentTo($officer, ResetPassword::class);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.password_link_sent', 'auditable_id' => $officer->id]);
    }

    /**
     * @return array<string, array{Role}>
     */
    public static function nonAdminRoles(): array
    {
        return [
            'compliance officer' => [Role::ComplianceOfficer],
            'executive reviewer' => [Role::ExecutiveReviewer],
        ];
    }

    #[DataProvider('nonAdminRoles')]
    public function test_other_roles_cannot_manage_users(Role $role): void
    {
        $user = User::factory()->create(['role' => $role]);
        $other = User::factory()->create();

        $this->signIn($user)->get(route('admin.users.index'))->assertForbidden();
        $this->signIn($user)->post(route('admin.users.store'), ['name' => 'X', 'email' => 'x@example.com', 'role' => 'admin'])->assertForbidden();
        $this->signIn($user)->delete(route('admin.users.deactivate', $other))->assertForbidden();

        $this->assertTrue($other->fresh()->is_active);
        $this->assertDatabaseMissing('users', ['email' => 'x@example.com']);
    }
}
