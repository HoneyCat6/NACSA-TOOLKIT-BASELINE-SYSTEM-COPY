<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditCategory;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Models\Setting;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin-editable settings (DECISIONS D-51): organisation name for the report cover, report footer text.
 */
class SettingsController extends Controller
{
    public function edit(Request $request): View
    {
        abort_unless($request->user()->hasRole(Role::Admin), 403);

        return view('admin.settings.edit', [
            'organisationName' => Setting::valueOf(Setting::OrganisationName),
            'reportFooter' => Setting::valueOf(Setting::ReportFooter),
        ]);
    }

    public function update(UpdateSettingsRequest $request, AuditService $audit): RedirectResponse
    {
        $values = [
            Setting::OrganisationName => $request->string('organisation_name')->trim()->toString(),
            Setting::ReportFooter => $request->filled('report_footer') ? $request->string('report_footer')->trim()->toString() : null,
        ];

        $changes = collect($values)
            ->filter(fn (?string $value, string $key): bool => $value !== Setting::valueOf($key))
            ->map(fn (?string $value, string $key): array => ['from' => Setting::valueOf($key), 'to' => $value])
            ->all();

        if ($changes === []) {
            return back()->with('status', 'No changes to save.');
        }

        foreach (array_keys($changes) as $key) {
            Setting::store($key, $values[$key]);
        }

        $audit->log('settings.updated', AuditCategory::Technical, metadata: ['changes' => $changes]);

        return back()->with('status', 'Settings saved.');
    }
}
