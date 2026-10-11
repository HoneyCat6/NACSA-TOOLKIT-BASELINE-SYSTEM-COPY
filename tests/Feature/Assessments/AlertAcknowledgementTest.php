<?php

namespace Tests\Feature\Assessments;

use App\Models\DegradationAlert;
use App\Models\NcsbElement;
use App\Models\User;
use Database\Seeders\NcsbElementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AlertAcknowledgementTest extends TestCase
{
    use RefreshDatabase;

    public function test_officer_acknowledges_an_alert_which_moves_to_history(): void
    {
        $this->freezeSecond();
        $officer = User::factory()->complianceOfficer()->create(['name' => 'Aina Officer']);
        $alert = DegradationAlert::factory()->create();

        $this->signIn($officer)->from('/trends')->post(route('alerts.acknowledge', $alert))
            ->assertRedirect('/trends')
            ->assertSessionHas('status', 'The Protect alert was acknowledged.');

        $alert->refresh();
        $this->assertTrue($alert->acknowledged);
        $this->assertSame($officer->id, $alert->acknowledged_by);
        $this->assertTrue($alert->acknowledged_at->equalTo(now()));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'alert.acknowledge',
            'user_id' => $officer->id,
            'auditable_id' => $alert->id,
            'category' => 'compliance',
        ]);

        $response = $this->signIn($officer)->get('/trends');
        $this->assertTrue($response->viewData('activeAlerts')->isEmpty());
        $this->assertTrue($response->viewData('acknowledgedAlerts')->contains($alert));
        $response->assertSeeText('Acknowledged by Aina Officer');
    }

    public function test_acknowledging_twice_keeps_the_original_acknowledgement(): void
    {
        $alert = DegradationAlert::factory()->acknowledged()->create();
        $originalAcknowledger = $alert->acknowledged_by;

        $this->signIn(User::factory()->complianceOfficer()->create())
            ->post(route('alerts.acknowledge', $alert))
            ->assertSessionHas('status', 'This alert was already acknowledged.');

        $this->assertSame($originalAcknowledger, $alert->fresh()->acknowledged_by);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'alert.acknowledge']);
    }

    public function test_executive_reviewer_cannot_acknowledge(): void
    {
        $alert = DegradationAlert::factory()->create();
        $executive = User::factory()->executiveReviewer()->create();

        $this->signIn($executive)->post(route('alerts.acknowledge', $alert))->assertForbidden();
        $this->signIn($executive)->post(route('alerts.acknowledge-all'))->assertForbidden();

        $this->assertFalse($alert->fresh()->acknowledged);
    }

    public function test_officer_acknowledges_an_element_alert(): void
    {
        $this->seed(NcsbElementSeeder::class);
        $alert = DegradationAlert::factory()->forElement(NcsbElement::query()->where('code', 'PR-03')->sole(), 2)->create();

        $this->signIn(User::factory()->complianceOfficer()->create())->from('/trends')
            ->post(route('alerts.acknowledge', $alert))
            ->assertSessionHas('status', 'The PR-03 alert was acknowledged.');

        $this->assertTrue($alert->fresh()->acknowledged);
        $metadata = json_decode(DB::table('audit_logs')->where('action', 'alert.acknowledge')->value('metadata'), true);
        $this->assertSame(['domain' => 'PROTECT', 'element_code' => 'PR-03'], $metadata);
    }

    public function test_acknowledge_all_clears_every_active_alert_and_audits_each(): void
    {
        $this->seed(NcsbElementSeeder::class);
        $officer = User::factory()->complianceOfficer()->create();
        $domainAlert = DegradationAlert::factory()->create();
        $elementAlert = DegradationAlert::factory()->forElement(NcsbElement::query()->where('code', 'PR-03')->sole())->create();
        $alreadyAcknowledged = DegradationAlert::factory()->acknowledged()->create();

        $this->signIn($officer)->from('/trends')->post(route('alerts.acknowledge-all'))
            ->assertRedirect('/trends')
            ->assertSessionHas('status', '2 alerts acknowledged.');

        $this->assertSame($officer->id, $domainAlert->fresh()->acknowledged_by);
        $this->assertSame($officer->id, $elementAlert->fresh()->acknowledged_by);
        $this->assertNotSame($officer->id, $alreadyAcknowledged->fresh()->acknowledged_by);
        $this->assertSame(
            [$domainAlert->id, $elementAlert->id],
            DB::table('audit_logs')->where('action', 'alert.acknowledge')->orderBy('auditable_id')->pluck('auditable_id')->all(),
        );
    }
}
