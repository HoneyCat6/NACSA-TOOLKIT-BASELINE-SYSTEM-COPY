<?php

namespace App\Http\Controllers;

use App\Enums\NotificationType;
use App\Models\ComplianceDeadline;
use App\Models\DeadlineNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * FR-45 notification history: every reminder and escalation, with its delivery flag (FR-46).
 */
class NotificationController extends Controller
{
    public const DeliveryFilters = ['delivered', 'pending', 'failed'];

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', ComplianceDeadline::class);

        $filters = $request->validate([
            'type' => ['nullable', Rule::enum(NotificationType::class)],
            'delivery' => ['nullable', Rule::in(self::DeliveryFilters)],
        ]);

        $notifications = DeadlineNotification::query()
            ->with(['deadline', 'recipient'])
            ->when($filters['type'] ?? null, fn ($query, string $type) => $query->where('type', $type))
            ->when($filters['delivery'] ?? null, fn ($query, string $delivery) => match ($delivery) {
                'delivered' => $query->where('delivered', true),
                'pending' => $query->where('delivered', false)->whereNull('failure_reason'),
                'failed' => $query->failed(),
            })
            ->orderByDesc('dispatched_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('notifications.index', [
            'notifications' => $notifications,
            'filters' => $filters,
            'failedCount' => DeadlineNotification::query()->failed()->count(),
        ]);
    }
}
