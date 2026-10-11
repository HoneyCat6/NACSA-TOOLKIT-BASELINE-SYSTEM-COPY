<?php

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * DECISIONS D-51.
 */
class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_updates_organisation_name_and_footer_and_the_change_is_audited(): void
    {
        Setting::store(Setting::OrganisationName, 'Your Organisation');
        $admin = User::factory()->admin()->create();

        $this->signIn($admin)->get(route('admin.settings.edit'))->assertOk()->assertSee('Your Organisation');

        $this->signIn($admin)->from(route('admin.settings.edit'))->put(route('admin.settings.update'), [
            'organisation_name' => '  Malaysia Airports Holdings Berhad ',
            'report_footer' => 'CONFIDENTIAL',
        ])->assertRedirect(route('admin.settings.edit'))->assertSessionHas('status', 'Settings saved.');

        $this->assertSame('Malaysia Airports Holdings Berhad', Setting::valueOf(Setting::OrganisationName));
        $this->assertSame('CONFIDENTIAL', Setting::valueOf(Setting::ReportFooter));

        $metadata = json_decode(DB::table('audit_logs')->where('action', 'settings.updated')->value('metadata'), true);
        $this->assertSame(['from' => 'Your Organisation', 'to' => 'Malaysia Airports Holdings Berhad'], $metadata['changes']['organisation_name']);
    }

    public function test_organisation_name_is_required(): void
    {
        $this->signIn(User::factory()->admin()->create())
            ->put(route('admin.settings.update'), ['organisation_name' => ''])
            ->assertSessionHasErrors('organisation_name');
    }

    public function test_other_roles_cannot_change_settings(): void
    {
        $officer = User::factory()->complianceOfficer()->create();

        $this->signIn($officer)->get(route('admin.settings.edit'))->assertForbidden();
        $this->signIn($officer)->put(route('admin.settings.update'), ['organisation_name' => 'Hacked'])->assertForbidden();

        $this->assertNull(Setting::valueOf(Setting::OrganisationName));
    }
}
