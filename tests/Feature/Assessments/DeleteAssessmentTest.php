<?php

namespace Tests\Feature\Assessments;

use App\Models\Assessment;
use App\Models\User;
use Database\Seeders\DemoAssessmentSeeder;
use Database\Seeders\NcsbElementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DeleteAssessmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_officer_deletes_the_latest_cycle_with_its_results_trend_alerts_and_file(): void
    {
        Storage::fake('local');
        $officer = $this->seedDemoCycles();
        $latest = Assessment::query()->newestFirst()->first();
        Storage::disk('local')->put($latest->stored_path, 'xlsx');
        $trendId = $latest->trend->id;

        $this->signIn($officer)->delete(route('assessments.destroy', $latest), ['reason' => 'Wrong file uploaded'])
            ->assertRedirect(route('assessments.index'))
            ->assertSessionHas('status', 'Cycle 2026 H1 was deleted. You can upload a corrected file as a new cycle.');

        $this->assertModelMissing($latest);
        $this->assertDatabaseMissing('assessment_elements', ['assessment_id' => $latest->id]);
        $this->assertDatabaseMissing('assessment_trends', ['id' => $trendId]);
        $this->assertDatabaseMissing('degradation_alerts', ['assessment_trend_id' => $trendId]);
        Storage::disk('local')->assertMissing($latest->stored_path);

        $this->assertSame(3, Assessment::query()->count());
        $this->assertDatabaseCount('assessment_trends', 2);
        $this->assertDatabaseCount('degradation_alerts', 1);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'assessment.deleted',
            'user_id' => $officer->id,
            'auditable_id' => $latest->id,
            'category' => 'compliance',
        ]);
        $metadata = json_decode(DB::table('audit_logs')->where('action', 'assessment.deleted')->value('metadata'), true);
        $this->assertSame('Cycle 2026 H1', $metadata['cycle_label']);
        $this->assertSame('Wrong file uploaded', $metadata['reason']);
    }

    public function test_the_previous_cycle_becomes_deletable_once_the_latest_is_gone(): void
    {
        $officer = $this->seedDemoCycles();

        $this->signIn($officer)->delete(route('assessments.destroy', Assessment::query()->newestFirst()->first()), ['reason' => 'Wrong file uploaded']);

        $this->signIn($officer)
            ->delete(route('assessments.destroy', Assessment::query()->where('cycle_label', 'Cycle 2025 H2')->sole()), ['reason' => 'Also a draft'])
            ->assertRedirect(route('assessments.index'));

        $this->assertSame(['Cycle 2024 H2', 'Cycle 2025 H1'], Assessment::query()->chronological()->pluck('cycle_label')->all());
    }

    public function test_an_earlier_cycle_cannot_be_deleted(): void
    {
        $officer = $this->seedDemoCycles();
        $earlier = Assessment::query()->where('cycle_label', 'Cycle 2025 H1')->sole();

        $this->signIn($officer)->delete(route('assessments.destroy', $earlier), ['reason' => 'Wrong file uploaded'])
            ->assertForbidden();

        $this->assertModelExists($earlier);
    }

    public function test_a_reason_is_required(): void
    {
        $officer = $this->seedDemoCycles();
        $latest = Assessment::query()->newestFirst()->first();

        $this->signIn($officer)->delete(route('assessments.destroy', $latest), ['reason' => ''])
            ->assertSessionHasErrors(['reason' => 'Give a reason for deleting this cycle. It is kept in the audit log.']);

        $this->assertModelExists($latest);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'assessment.deleted']);
    }

    public function test_executive_reviewer_cannot_delete(): void
    {
        $assessment = Assessment::factory()->create();

        $this->signIn(User::factory()->executiveReviewer()->create())
            ->delete(route('assessments.destroy', $assessment), ['reason' => 'Wrong file uploaded'])
            ->assertForbidden();

        $this->assertModelExists($assessment);
    }

    public function test_results_screen_offers_deletion_only_on_the_latest_cycle(): void
    {
        $officer = User::factory()->complianceOfficer()->create();
        $earlier = Assessment::factory()->create(['assessment_date' => '2025-06-01']);
        $latest = Assessment::factory()->create(['assessment_date' => '2026-01-01']);

        $this->signIn($officer)->get(route('assessments.show', $latest))
            ->assertSeeText('Delete this cycle');
        $this->signIn($officer)->get(route('assessments.show', $earlier))
            ->assertDontSeeText('Delete this cycle')
            ->assertSeeText('Only the latest cycle can be deleted');
        $this->signIn(User::factory()->executiveReviewer()->create())->get(route('assessments.show', $latest))
            ->assertDontSeeText('Delete this cycle')
            ->assertDontSeeText('Only the latest cycle can be deleted');
    }

    public function test_cycle_label_is_escaped_in_the_delete_confirmation(): void
    {
        $assessment = Assessment::factory()->create(['cycle_label' => "O'Brien');alert(1);//"]);

        $html = $this->signIn(User::factory()->complianceOfficer()->create())
            ->get(route('assessments.show', $assessment))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, preg_match('/@submit="([^"]*confirm\(([^"]*)\)\)[^"]*)"/', $html, $match));

        // The browser decodes HTML entities in the attribute before running it, so check the decoded JavaScript:
        // the only apostrophes left must be the string's own delimiters, so the label cannot end the string early.
        $confirmArgument = html_entity_decode($match[2], ENT_QUOTES);
        $this->assertSame(2, substr_count($confirmArgument, "'"));
        $this->assertStringStartsWith("'", $confirmArgument);
        $this->assertStringEndsWith("'", $confirmArgument);
    }

    private function seedDemoCycles(): User
    {
        $officer = User::factory()->complianceOfficer()->create();
        $this->seed([NcsbElementSeeder::class, DemoAssessmentSeeder::class]);

        return $officer;
    }
}
