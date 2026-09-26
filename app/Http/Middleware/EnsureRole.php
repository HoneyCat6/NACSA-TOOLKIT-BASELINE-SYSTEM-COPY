<?php

namespace App\Http\Middleware;

use App\Enums\AuditCategory;
use App\Services\AuditService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level role gate (FR-04, SEC-03). Usage: `role:compliance_officer,executive_reviewer`.
 */
class EnsureRole
{
    public function __construct(private AuditService $audit) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! in_array($request->user()?->role?->value, $roles, true)) {
            $this->audit->log('access.denied', AuditCategory::Technical, metadata: [
                'route' => $request->route()?->getName(),
                'path' => $request->path(),
            ]);

            abort(403);
        }

        return $next($request);
    }
}
