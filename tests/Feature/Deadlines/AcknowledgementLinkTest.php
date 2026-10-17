<?php

namespace Tests\Feature\Deadlines;

use App\Mail\DeadlineReminderMail;
use App\Models\ComplianceDeadline;
use App\Models\DeadlineNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * FR-43 signed acknowledgement link (DECISIONS D-45): works without signing in, and needs a confirming click.
 */
class AcknowledgementLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-01 10:00:00');
    }

    public function test_the_reminder_email_carries_a_working_link(): void
    {
        $deadline = ComplianceDeadline::factory()->dueIn(7)->create();
        $notification = DeadlineNotification::factory()->for($deadline, 'deadline')->create(['lead_day' => 7]);

        $text = (new DeadlineReminderMail($notification))->render();
        preg_match('~https?://\S+/acknowledge/\S+~', $text, $match);

        $this->get($match[0])
            ->assertOk()
            ->assertSeeText("Acknowledge as {$deadline->officer->name}")
            ->assertSee('Acknowledge deadline');
    }

    public function test_opening_the_link_does_not_acknowledge(): void
    {
        $deadline = ComplianceDeadline::factory()->dueIn(7)->create();

        $this->get($this->link($deadline))->assertOk();

        $this->assertNull($deadline->fresh()->acknowledged_at);
    }

    public function test_confirming_acknowledges_as_the_recipient_without_signing_in(): void
    {
        $deadline = ComplianceDeadline::factory()->dueIn(7)->create();
        $link = $this->link($deadline);

        $this->post($link)
            ->assertRedirect($link)
            ->assertSessionHas('status', 'Thank you. The deadline is acknowledged and will not be escalated.');

        $deadline->refresh();
        $this->assertNotNull($deadline->acknowledged_at);
        $this->assertSame($deadline->officer_id, $deadline->acknowledged_by);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'deadline.acknowledged',
            'user_id' => $deadline->officer_id,
            'auditable_id' => $deadline->id,
            'category' => 'compliance',
        ]);
        $this->assertSame('email link', json_decode(DB::table('audit_logs')->value('metadata'), true)['via']);

        $this->get($link)->assertSeeText('Acknowledged on 1 Oct 2026, 10:00');
    }

    public function test_a_tampered_link_is_refused(): void
    {
        $deadline = ComplianceDeadline::factory()->dueIn(7)->create();
        $otherOfficer = User::factory()->complianceOfficer()->create();
        $tampered = str_replace("/acknowledge/{$deadline->officer_id}", "/acknowledge/{$otherOfficer->id}", $this->link($deadline));

        $this->get($tampered)->assertForbidden();
        $this->post($tampered)->assertForbidden();

        $this->assertNull($deadline->fresh()->acknowledged_at);
    }

    public function test_an_expired_link_cannot_acknowledge(): void
    {
        $deadline = ComplianceDeadline::factory()->dueIn(7)->create();
        $link = $this->link($deadline);

        $this->travel(8)->days();

        $this->post($link)->assertOk()->assertSeeText('This link expired at the end of the due date.');
        $this->assertNull($deadline->fresh()->acknowledged_at);
    }

    /**
     * D-44: after the due date moves, a link from an earlier reminder no longer acknowledges.
     */
    public function test_a_link_for_an_earlier_due_date_cannot_acknowledge(): void
    {
        $deadline = ComplianceDeadline::factory()->dueIn(7)->create();
        $link = $this->link($deadline);
        $deadline->update(['due_date' => today()->addDays(30)]);

        $this->post($link)->assertOk()->assertSeeText('The due date has changed since this reminder was sent.');
        $this->assertNull($deadline->fresh()->acknowledged_at);
    }

    public function test_a_deactivated_recipient_cannot_acknowledge(): void
    {
        $deadline = ComplianceDeadline::factory()->dueIn(7)->create();
        $deadline->officer->update(['is_active' => false]);

        $this->post($this->link($deadline))->assertOk()->assertSeeText('This link belongs to a deactivated account.');
        $this->assertNull($deadline->fresh()->acknowledged_at);
    }

    public function test_a_met_deadline_needs_no_acknowledgement(): void
    {
        $deadline = ComplianceDeadline::factory()->dueIn(7)->met()->create();

        $this->get($this->link($deadline))->assertOk()->assertSeeText('This deadline is already met.');
        $this->post($this->link($deadline))->assertSessionMissing('status');

        $this->assertNull($deadline->fresh()->acknowledged_at);
    }

    private function link(ComplianceDeadline $deadline): string
    {
        return URL::temporarySignedRoute(
            'deadlines.acknowledge-link',
            $deadline->due_date->copy()->endOfDay(),
            ['deadline' => $deadline, 'user' => $deadline->officer_id, 'due' => $deadline->due_date->toDateString()],
        );
    }
}
