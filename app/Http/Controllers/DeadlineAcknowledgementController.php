<?php

namespace App\Http\Controllers;

use App\Models\ComplianceDeadline;
use App\Models\User;
use App\Services\DeadlineService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

/**
 * FR-43: the signed acknowledgement link from a reminder email. It works without signing in (DECISIONS D-45).
 *
 * Opening the link shows a confirmation button rather than acknowledging straight away, so mail scanners that
 * prefetch links cannot acknowledge on the recipient's behalf.
 */
class DeadlineAcknowledgementController extends Controller
{
    public function __construct(private DeadlineService $deadlines) {}

    public function show(Request $request, ComplianceDeadline $deadline, User $user): View
    {
        return view('deadlines.acknowledge', [
            'deadline' => $deadline->load('acknowledgedBy'),
            'user' => $user,
            'problem' => $this->problem($request, $deadline, $user),
        ]);
    }

    public function store(Request $request, ComplianceDeadline $deadline, User $user): RedirectResponse|View
    {
        if ($this->problem($request, $deadline, $user) !== null) {
            return $this->show($request, $deadline, $user);
        }

        $redirect = redirect()->to($request->fullUrl());

        return $this->deadlines->acknowledge($deadline, $user, 'email link')
            ? $redirect->with('status', 'Thank you. The deadline is acknowledged and will not be escalated.')
            : $redirect;
    }

    /**
     * Why this link cannot acknowledge, or null when it can (or there is nothing left to do).
     * A tampered link is refused outright.
     */
    private function problem(Request $request, ComplianceDeadline $deadline, User $user): ?string
    {
        abort_unless(URL::hasCorrectSignature($request), 403);

        return match (true) {
            ! $user->is_active => 'This link belongs to a deactivated account.',
            $deadline->isMet(), $deadline->isAcknowledged() => null,
            $request->query('due') !== $deadline->due_date->toDateString() => 'The due date has changed since this reminder was sent. Use the link in the latest reminder, or sign in to acknowledge it.',
            ! URL::signatureHasNotExpired($request) => 'This link expired at the end of the due date. Sign in to see the deadline.',
            default => null,
        };
    }
}
