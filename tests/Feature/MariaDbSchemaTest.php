<?php

namespace Tests\Feature;

use App\Models\ComplianceDeadline;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Guards the MariaDB constraints in PRD §9.4 / Tech Spec §1.3.1.
 */
class MariaDbSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_connection_targets_mariadb(): void
    {
        $this->assertSame('mariadb', DB::connection()->getDriverName());
        $this->assertStringContainsString('MariaDB', DB::scalar('select version()'));
    }

    public function test_every_table_uses_innodb_and_utf8mb4_unicode_ci(): void
    {
        // WampServer's MariaDB defaults to MyISAM, which silently drops foreign keys and transactions.
        foreach (Schema::getTables() as $table) {
            $this->assertSame('InnoDB', $table['engine'], "{$table['name']} engine");
            $this->assertSame('utf8mb4_unicode_ci', $table['collation'], "{$table['name']} collation");
        }
    }

    public function test_encrypted_columns_are_text_not_json(): void
    {
        // Ciphertext is not valid JSON, so MariaDB's json_valid() CHECK would reject it.
        $this->assertSame('text', Schema::getColumnType('assessment_elements', 'responses'));
        $this->assertSame('text', Schema::getColumnType('simulation_sessions', 'modified_responses'));
        $this->assertSame('text', Schema::getColumnType('executive_reports', 'payload'));
    }

    public function test_lead_days_default_comes_from_the_model_not_the_column(): void
    {
        $leadDays = collect(Schema::getColumns('compliance_deadlines'))->firstWhere('name', 'lead_days');

        $this->assertTrue($leadDays['nullable']);
        $this->assertContains($leadDays['default'], [null, 'NULL']);

        $deadline = ComplianceDeadline::query()->create([
            'officer_id' => User::factory()->complianceOfficer()->create()->id,
            'title' => 'Annual NCSB self-assessment',
            'due_date' => now()->addMonth(),
        ]);

        $this->assertSame(ComplianceDeadline::DefaultLeadDays, $deadline->fresh()->lead_days);
    }
}
