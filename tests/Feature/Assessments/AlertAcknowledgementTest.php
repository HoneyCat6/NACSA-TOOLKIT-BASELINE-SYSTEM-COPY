<?php

namespace Tests\Feature\Assessments;

use App\Models\DegradationAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $this->signIn(User::factory()->executiveReviewer()->create())
            ->post(route('alerts.acknowledge', $alert))
            ->assertForbidden();

        $this->assertFalse($alert->fresh()->acknowledged);
    }
}
