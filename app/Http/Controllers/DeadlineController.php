<?php

namespace App\Http\Controllers;

use App\Enums\AuditCategory;
use App\Enums\Role;
use App\Http\Requests\StoreDeadlineRequest;
use App\Http\Requests\UpdateDeadlineRequest;
use App\Models\ComplianceDeadline;
use App\Models\DeadlineNotification;
use App\Models\User;
use App\Services\AuditService;
use App\Services\DeadlineService;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Compliance deadline tracker (FR-40, FR-43, FR-44, DECISIONS D-40 – D-47).
 */
class DeadlineController extends Controller
{
    public function __construct(private AuditService $audit, private DeadlineService $deadlines) {}

    public function index(): View
    {
        Gate::authorize('viewAny', ComplianceDeadline::class);

        $deadlines = ComplianceDeadline::query()
            ->with(['officer', 'secondaryContact', 'acknowledgedBy', 'metByAssessment', 'notifications'])
            ->get();

        return view('deadlines.index', [
            'openDeadlines' => $deadlines->reject->isMet()->sortBy('due_date')->values(),
            'metDeadlines' => $deadlines->filter->isMet()->sortByDesc('met_at')->values(),
            'failedNotificationCount' => DeadlineNotification::query()->failed()->count(),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', ComplianceDeadline::class);

        return view('deadlines.create', [
            'deadline' => new ComplianceDeadline(['officer_id' => $request->user()->id]),
            ...$this->contactOptions(),
        ]);
    }

    public function store(StoreDeadlineRequest $request): RedirectResponse
    {
        $deadline = ComplianceDeadline::query()->create([
            ...$request->safe()->only(['title', 'description', 'due_date', 'officer_id', 'secondary_contact_id']),
            'lead_days' => $request->leadDays(),
        ]);

        $this->audit->log('deadline.created', AuditCategory::Compliance, $deadline, [
            'title' => $deadline->title,
            'due_date' => $deadline->due_date->toDateString(),
            'lead_days' => $deadline->lead_days,
        ]);

        return redirect()
            ->route('deadlines.index')
            ->with('status', "\"{$deadline->title}\" was added. Reminders go to {$deadline->officer->name} {$this->leadDayList($deadline)} before {$deadline->due_date->format('j M Y')}.");
    }

    public function edit(ComplianceDeadline $deadline): View
    {
        Gate::authorize('update', $deadline);

        return view('deadlines.edit', [
            'deadline' => $deadline,
            ...$this->contactOptions(),
        ]);
    }

    /**
     * D-44: a new due date starts the reminder intervals over and clears the acknowledgement.
     * Notifications already sent stay in the history.
     */
    public function update(UpdateDeadlineRequest $request, ComplianceDeadline $deadline): RedirectResponse
    {
        $deadline->fill([
            ...$request->safe()->only(['title', 'description', 'due_date', 'officer_id', 'secondary_contact_id']),
            'lead_days' => $request->leadDays(),
        ]);

        $rearmed = $deadline->isDirty('due_date') && ! $deadline->isMet();

        if ($rearmed) {
            $deadline->fill(['acknowledged_at' => null, 'acknowledged_by' => null]);
        }

        $changes = collect($deadline->getDirty())
            ->except(['acknowledged_at', 'acknowledged_by'])
            ->mapWithKeys(fn (mixed $value, string $field): array => [$field => [
                'from' => $this->auditValue($deadline->getOriginal($field)),
                'to' => $this->auditValue($deadline->{$field}),
            ]])
            ->all();

        if ($changes === []) {
            return redirect()->route('deadlines.index')->with('status', "No changes to \"{$deadline->title}\".");
        }

        $deadline->save();

        $this->audit->log('deadline.updated', AuditCategory::Compliance, $deadline, [
            'changes' => $changes,
            'reminders_rearmed' => $rearmed,
        ]);

        return redirect()
            ->route('deadlines.index')
            ->with('status', $rearmed
                ? "\"{$deadline->title}\" was updated. Reminders start over for the new due date, {$deadline->due_date->format('j M Y')}."
                : "\"{$deadline->title}\" was updated.");
    }

    public function destroy(ComplianceDeadline $deadline): RedirectResponse
    {
        Gate::authorize('delete', $deadline);

        $this->audit->log('deadline.deleted', AuditCategory::Compliance, $deadline, [
            'title' => $deadline->title,
            'due_date' => $deadline->due_date->toDateString(),
        ]);

        $deadline->delete();

        return redirect()->route('deadlines.index')->with('status', "\"{$deadline->title}\" was deleted.");
    }

    public function acknowledge(Request $request, ComplianceDeadline $deadline): RedirectResponse
    {
        Gate::authorize('acknowledge', $deadline);

        if (! $this->deadlines->acknowledge($deadline, $request->user(), 'deadlines page')) {
            return back()->with('status', "\"{$deadline->title}\" was already acknowledged or met.");
        }

        return back()->with('status', "\"{$deadline->title}\" was acknowledged. It will not be escalated; reminders continue until it is met.");
    }

    public function markMet(Request $request, ComplianceDeadline $deadline): RedirectResponse
    {
        Gate::authorize('markMet', $deadline);

        if ($deadline->isMet()) {
            return back()->with('status', "\"{$deadline->title}\" was already met.");
        }

        $this->deadlines->markMet($deadline, $request->user());

        return back()->with('status', "\"{$deadline->title}\" was marked as met. No further reminders or escalation will be sent.");
    }

    /**
     * D-47: the primary contact is an active Compliance Officer; the escalation contact may also be an Executive Reviewer.
     *
     * @return array{officers: Collection<int, User>, escalationContacts: Collection<int, User>}
     */
    private function contactOptions(): array
    {
        $contacts = User::query()
            ->where('is_active', true)
            ->whereIn('role', [Role::ComplianceOfficer, Role::ExecutiveReviewer])
            ->orderBy('name')
            ->get();

        return [
            'officers' => $contacts->filter(fn (User $user): bool => $user->hasRole(Role::ComplianceOfficer))->values(),
            'escalationContacts' => $contacts,
        ];
    }

    private function leadDayList(ComplianceDeadline $deadline): string
    {
        return collect($deadline->leadDays())->join(', ', ' and ').' days';
    }

    private function auditValue(mixed $value): mixed
    {
        return $value instanceof CarbonInterface ? $value->toDateString() : $value;
    }
}
