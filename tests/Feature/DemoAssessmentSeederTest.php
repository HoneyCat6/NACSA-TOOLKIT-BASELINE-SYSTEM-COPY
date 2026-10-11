<?php

namespace Tests\Feature;

use App\Enums\Band;
use App\Enums\Domain;
use App\Models\Assessment;
use App\Models\DegradationAlert;
use App\Models\User;
use Database\Seeders\DemoAssessmentSeeder;
use Database\Seeders\NcsbElementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoAssessmentSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_four_cycles_with_trends_and_alerts(): void
    {
        User::factory()->complianceOfficer()->create();
        $this->seed([NcsbElementSeeder::class, DemoAssessmentSeeder::class]);

        $assessments = Assessment::query()->chronological()->get();
        $this->assertSame(['Cycle 2024 H2', 'Cycle 2025 H1', 'Cycle 2025 H2', 'Cycle 2026 H1'], $assessments->pluck('cycle_label')->all());
        $this->assertSame([Band::Basic, Band::Intermediate, Band::Advanced, Band::Advanced], $assessments->pluck('overall_band')->all());
        $this->assertSame(33 * 4, $assessments->sum(fn (Assessment $assessment) => $assessment->elements()->count()));
        $this->assertDatabaseCount('assessment_trends', 3);

        $domainAlerts = DegradationAlert::query()->whereNull('ncsb_element_id');
        $this->assertSame([Domain::Protect, Domain::Respond], (clone $domainAlerts)->active()->orderBy('domain')->pluck('domain')->all());
        $this->assertSame([Domain::Identify], (clone $domainAlerts)->where('acknowledged', true)->pluck('domain')->all());

        // D-36: every element that dropped a level has its own alert — 7 PROTECT elements and RS-01 in 2026 H1, both IDENTIFY elements in 2025 H2.
        $elementAlerts = DegradationAlert::query()->whereNotNull('ncsb_element_id')->with('ncsbElement');
        $this->assertSame(['PR-02', 'PR-04', 'PR-07', 'PR-10', 'PR-13', 'PR-15', 'PR-18', 'RS-01'], (clone $elementAlerts)->active()->get()->map->subjectLabel()->sort()->values()->all());
        $this->assertSame(['ID-01', 'ID-02'], (clone $elementAlerts)->where('acknowledged', true)->get()->map->subjectLabel()->sort()->values()->all());
    }

    public function test_running_twice_does_not_duplicate_cycles(): void
    {
        User::factory()->complianceOfficer()->create();
        $this->seed([NcsbElementSeeder::class, DemoAssessmentSeeder::class, DemoAssessmentSeeder::class]);

        $this->assertDatabaseCount('assessments', 4);
        $this->assertDatabaseCount('degradation_alerts', 13);
    }
}
