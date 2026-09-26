<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects any authenticated session that did not complete the email OTP step (SEC-02).
 */
class EnsureMfaVerified
{
    public const SessionKey = 'mfa_passed';

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $mfaRequired = (bool) config('security.otp.enabled', true);

        if (($mfaRequired && $request->session()->get(self::SessionKey) !== true) || ! $request->user()?->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }

        return $next($request);
    }
}
