<?php

/*
|--------------------------------------------------------------------------
| NCSB v1.4 toolkit layout
|--------------------------------------------------------------------------
|
| Cell map of the official NCSB Self-Assessment v1.4 workbook (PRD
| Appendix A, DECISIONS D-10). Per-element response ranges, thresholds
| and question texts live in database/seeders/data/ncsb-v1.4.json and
| are seeded into `ncsb_elements`.
|
*/

return [

    'template_version' => env('NCSB_TEMPLATE_VERSION', 'v1.4'),

    'reference_data' => database_path('seeders/data/ncsb-v1.4.json'),

    'sheets' => [
        'Using this CS Baseline Tool',
        'CS Baseline Model',
        'CS Baseline Questionnaires',
        'CS Baseline Scoring Dashboard',
        'CS Baseline Maturity Level ',
        'FAQ',
    ],

    'questionnaire' => [
        'sheet' => 'CS Baseline Questionnaires',
        'first_row' => 3,
        'last_row' => 127,
        'element_column' => 'E',
        'question_column' => 'G',
        'response_column' => 'H',
    ],

    'question_count' => 125,

    'element_count' => 33,

    'max_upload_kb' => (int) env('ASSESSMENT_MAX_SIZE_KB', 10240),

    'report_top_actions' => (int) env('REPORT_TOP_ACTIONS', 10),

];
