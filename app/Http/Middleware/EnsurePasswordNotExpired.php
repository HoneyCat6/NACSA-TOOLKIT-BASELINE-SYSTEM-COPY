<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Forces a password change when the password is older than the expiry period, or was set by an admin (FR-03).
 */
class EnsurePasswordNotExpired
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->passwordHasExpired()) {
            return redirect()->route('password.change')
                ->with('warning', 'Your password has expired. Please choose a new one to continue.');
        }

        return $next($request);
    }
}
