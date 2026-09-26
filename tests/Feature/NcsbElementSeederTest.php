<?php

namespace Tests\Feature;

use App\Enums\Domain;
use App\Models\NcsbElement;
use Database\Seeders\NcsbElementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NcsbElementSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(NcsbElementSeeder::class);
    }

    public function test_seeds_33_elements_with_125_questions(): void
    {
        $this->assertSame(33, NcsbElement::query()->count());
        $this->assertSame(125, (int) NcsbElement::query()->sum('question_count'));
        $this->assertSame(15, NcsbElement::query()->distinct()->count('category'));
    }

    public function test_domain_element_counts_match_the_framework(): void
    {
        foreach (Domain::cases() as $domain) {
            $this->assertSame(
                $domain->elementCount(),
                NcsbElement::query()->where('domain', $domain)->count(),
                "{$domain->value} element count",
            );
        }
    }

    public function test_every_element_has_consistent_thresholds_rows_and_questions(): void
    {
        $expectedRow = config('ncsb.questionnaire.first_row');

        foreach (NcsbElement::query()->orderBy('sort_order')->get() as $element) {
            $this->assertSame($expectedRow, $element->first_row, "{$element->code} first row");
            $this->assertSame($element->question_count, $element->last_row - $element->first_row + 1);
            $this->assertCount($element->question_count, $element->questions);
            $this->assertTrue(
                $element->threshold_l1 >= 1
                && $element->threshold_l1 < $element->threshold_l2
                && $element->threshold_l2 < $element->threshold_l3
                && $element->threshold_l3 === $element->question_count,
                "{$element->code} thresholds ascend to the question count",
            );
            $this->assertStringStartsWith($element->domain->elementCodePrefix().'-', $element->code);
            $this->assertSame($element->domain->riskWeight(), $element->domain_risk_weight);

            $expectedRow = $element->last_row + 1;
        }

        $this->assertSame(config('ncsb.questionnaire.last_row') + 1, $expectedRow);
    }

    public function test_thresholds_follow_the_scoring_dashboard_formulas(): void
    {
        // DECISIONS D-15: these two differ from the Model sheet text; the formulas win.
        $networkControls = NcsbElement::query()->where('code', 'PR-14')->sole();
        $this->assertSame([1, 2, 4], [$networkControls->threshold_l1, $networkControls->threshold_l2, $networkControls->threshold_l3]);

        $vulnerabilityAssessment = NcsbElement::query()->where('code', 'PR-19')->sole();
        $this->assertSame([1, 3, 5], [$vulnerabilityAssessment->threshold_l1, $vulnerabilityAssessment->threshold_l2, $vulnerabilityAssessment->threshold_l3]);

        $networkPolicy = NcsbElement::query()->where('code', 'PR-12')->sole();
        $this->assertSame([3, 4, 5], [$networkPolicy->threshold_l1, $networkPolicy->threshold_l2, $networkPolicy->threshold_l3]);
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(NcsbElementSeeder::class);

        $this->assertSame(33, NcsbElement::query()->count());
    }
}
