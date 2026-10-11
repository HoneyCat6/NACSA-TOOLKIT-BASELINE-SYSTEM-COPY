<?php

namespace Tests\Feature\Admin;

use App\Enums\AuditCategory;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PRD §4.2 "View technical audit logs", DECISIONS D-54.
 */
class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_technical_entries_but_not_the_compliance_trail(): void
    {
        $admin = User::factory()->admin()->create();
        $this->log('login.failed', AuditCategory::Technical, metadata: ['email' => 'intruder@example.com']);
        $this->log('assessment.upload', AuditCategory::Compliance);

        $this->signIn($admin)->get(route('admin.audit-logs.index'))
            ->assertOk()
            ->assertSeeText('login.failed')
            ->assertSeeText('intruder@example.com')
            ->assertDontSeeText('assessment.upload');
    }

    public function test_filters_by_action_user_and_date(): void
    {
        $admin = User::factory()->admin()->create();
        $aina = User::factory()->create(['email' => 'aina@example.com']);
        $this->log('login.success', AuditCategory::Technical, $aina, '2026-10-01 09:00:00');
        $this->log('login.success', AuditCategory::Technical, $aina, '2026-09-01 09:00:00');
        $this->log('logout', AuditCategory::Technical, $aina, '2026-10-01 10:00:00');
        $this->log('login.success', AuditCategory::Technical, User::factory()->create(), '2026-10-01 11:00:00');

        $response = $this->signIn($admin)->get(route('admin.audit-logs.index', [
            'action' => 'login.success',
            'user' => 'aina@',
            'from' => '2026-10-01',
            'to' => '2026-10-01',
        ]))->assertOk();

        $logs = $response->viewData('logs');
        $this->assertSame(1, $logs->total());
        $this->assertSame('2026-10-01 09:00:00', $logs->first()->created_at->toDateTimeString());
    }

    public function test_compliance_officer_cannot_view_the_technical_log(): void
    {
        $this->signIn(User::factory()->complianceOfficer()->create())
            ->get(route('admin.audit-logs.index'))
            ->assertForbidden();
    }

    public function test_admin_dashboard_shows_user_counts_health_and_recent_activity(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->complianceOfficer()->count(2)->create();
        User::factory()->complianceOfficer()->inactive()->create();
        $this->log('login.failed', AuditCategory::Technical, metadata: ['email' => 'intruder@example.com']);

        $response = $this->signIn($admin)->get('/dashboard')->assertOk();

        $this->assertSame(['active' => 2, 'inactive' => 1], collect($response->viewData('roleCounts')['compliance_officer'])->only(['active', 'inactive'])->all());
        $this->assertSame(1, $response->viewData('failedLoginsToday'));
        $response->assertSeeText('Failed jobs')->assertSeeText('login.failed');
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function log(string $action, AuditCategory $category, ?User $user = null, ?string $at = null, array $metadata = []): void
    {
        AuditLog::query()->create([
            'user_id' => $user?->id,
            'action' => $action,
            'category' => $category,
            'metadata' => $metadata === [] ? null : $metadata,
            'created_at' => $at ?? now(),
        ]);
    }
}
