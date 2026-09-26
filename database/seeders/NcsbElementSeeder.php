<?php

namespace Database\Seeders;

use App\Enums\Domain;
use App\Models\NcsbElement;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Seeds the 33 NCSB v1.4 elements from the mapping extracted from the official toolkit (PRD Appendix A).
 */
class NcsbElementSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = json_decode(file_get_contents(config('ncsb.reference_data')), true, flags: JSON_THROW_ON_ERROR);
        $elements = $data['elements'];

        if (count($elements) !== config('ncsb.element_count')
            || array_sum(array_column($elements, 'question_count')) !== config('ncsb.question_count')) {
            throw new RuntimeException('NCSB reference data must contain 33 elements and 125 questions.');
        }

        foreach ($elements as $element) {
            $domain = Domain::from($element['domain']);

            NcsbElement::query()->updateOrCreate(['code' => $element['code']], [
                ...$element,
                'domain' => $domain,
                'domain_risk_weight' => $domain->riskWeight(),
            ]);
        }
    }
}
