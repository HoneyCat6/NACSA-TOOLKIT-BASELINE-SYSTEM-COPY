<?php

namespace App\Services;

use App\Enums\AuditCategory;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Writes `audit_logs` rows for every state-changing action (FR-06, SEC-06).
 */
class AuditService
{
    public function __construct(private Request $request) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function log(
        string $action,
        AuditCategory $category = AuditCategory::Compliance,
        ?Model $auditable = null,
        array $metadata = [],
        ?User $user = null,
    ): AuditLog {
        return AuditLog::query()->create([
            'user_id' => ($user ?? $this->request->user())?->id,
            'action' => $action,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'category' => $category,
            'ip_address' => $this->request->ip(),
            'user_agent' => Str::limit((string) $this->request->userAgent(), 250, ''),
            'metadata' => $metadata === [] ? null : $metadata,
            'created_at' => now(),
        ]);
    }
}
