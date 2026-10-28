<?php

namespace Tests\Feature\Dashboards;

use App\Models\User;
use App\Services\SystemHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * FR-70 Admin dashboard and its FR-73 first-run state (DECISIONS D-60, D-64).
 */
class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_scheduled_heartbeat_records_the_last_scheduler_run(): void
    {
        $this->travelTo('2026-10-18 10:07:00');

        $this->artisan('schedule:run')->assertSuccessful();

        $this->assertSame('2026-10-18 10:07:00', app(SystemHealthService::class)->lastSchedulerRun()?->format('Y-m-d H:i:s'));
    }

    public function test_dashboard_shows_the_scheduler_running_while_the_heartbeat_is_fresh(): void
    {
        $this->travelTo('2026-10-18 10:07:00');
        app(SystemHealthService::class)->recordSchedulerHeartbeat();
        $this->travelTo('2026-10-18 10:12:00');

        $this->signIn(User::factory()->admin()->create())
            ->get('/dashboard')
            ->assertSeeText('Running')
            ->assertDontSeeText('Not running')
            ->assertSeeText('Last run 18 Oct 2026, 10:07');
    }

    public function test_dashboard_flags_the_scheduler_once_the_heartbeat_is_stale(): void
    {
        $this->travelTo('2026-10-18 10:07:00');
        app(SystemHealthService::class)->recordSchedulerHeartbeat();
        $this->travelTo('2026-10-18 10:12:01');

        $this->signIn(User::factory()->admin()->create())
            ->get('/dashboard')
            ->assertSeeText('Not running')
            ->assertSeeText('Last run 18 Oct 2026, 10:07');
    }

    public function test_dashboard_says_when_the_scheduler_has_never_run(): void
    {
        $this->signIn(User::factory()->admin()->create())
            ->get('/dashboard')
            ->assertSeeText('Not running')
            ->assertSeeText('No run recorded');
    }

    public function test_dashboard_shows_queue_depth_and_failed_jobs(): void
    {
        DB::table('jobs')->insert(array_fill(0, 3, ['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'available_at' => now()->getTimestamp(), 'created_at' => now()->getTimestamp()]));
        DB::table('failed_jobs')->insert(['uuid' => fake()->uuid(), 'connection' => 'database', 'queue' => 'default', 'payload' => '{}', 'exception' => 'RuntimeException']);

        $response = $this->signIn(User::factory()->admin()->create())->get('/dashboard');

        $response->assertSeeInOrder(['Queued jobs', '3', 'Failed jobs', '1'], false);
    }

    public function test_first_run_dashboard_prompts_the_admin_to_add_a_compliance_officer(): void
    {
        User::factory()->complianceOfficer()->inactive()->create();

        $this->signIn(User::factory()->admin()->create())
            ->get('/dashboard')
            ->assertSeeText('no Compliance Officer account is active yet')
            ->assertSee(route('admin.users.create'));
    }

    public function test_the_first_run_prompt_disappears_once_an_officer_is_active(): void
    {
        User::factory()->complianceOfficer()->create();

        $this->signIn(User::factory()->admin()->create())
            ->get('/dashboard')
            ->assertDontSeeText('no Compliance Officer account is active yet');
    }
}
