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

        $this->assertSame([Domain::Protect, Domain::Respond], DegradationAlert::query()->active()->orderBy('domain')->pluck('domain')->all());
        $this->assertSame([Domain::Identify], DegradationAlert::query()->where('acknowledged', true)->pluck('domain')->all());
    }

    public function test_running_twice_does_not_duplicate_cycles(): void
    {
        User::factory()->complianceOfficer()->create();
        $this->seed([NcsbElementSeeder::class, DemoAssessmentSeeder::class, DemoAssessmentSeeder::class]);

        $this->assertDatabaseCount('assessments', 4);
        $this->assertDatabaseCount('degradation_alerts', 3);
    }
}
