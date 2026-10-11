<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Assessment;
use App\Models\DegradationAlert;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Dispatch to the dashboard for the user's role (FR-70 – FR-73).
     */
    public function index(Request $request): View
    {
        return match ($request->user()->role) {
            Role::Admin => view('dashboard.admin'),
            Role::ComplianceOfficer => view('dashboard.officer', [
                'latest' => $this->latestAssessment(),
                'activeAlertCount' => DegradationAlert::query()->active()->count(),
            ]),
            Role::ExecutiveReviewer => view('dashboard.executive', [
                'latest' => $this->latestAssessment(),
            ]),
        };
    }

    private function latestAssessment(): ?Assessment
    {
        return Assessment::query()->newestFirst()->first();
    }
}
