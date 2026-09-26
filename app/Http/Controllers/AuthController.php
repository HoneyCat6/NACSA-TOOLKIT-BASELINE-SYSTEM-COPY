<?php

namespace App\Http\Controllers;

use App\Enums\AuditCategory;
use App\Enums\OtpResult;
use App\Http\Middleware\EnsureMfaVerified;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Models\User;
use App\Services\AuditService;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Password + email OTP login (FR-01 – FR-03, TECH_SPEC §9).
 */
class AuthController extends Controller
{
    private const PendingUserKey = 'mfa_pending_user_id';

    public function __construct(private AuditService $audit, private OtpService $otp) {}

    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->only('email', 'password');
        $user = User::query()->where('email', $credentials['email'])->first();

        if ($user === null || ! Auth::validate($credentials)) {
            $this->audit->log('login.failed', AuditCategory::Technical, $user, ['email' => $credentials['email']], $user);

            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'These credentials do not match our records.']);
        }

        if (! $user->is_active) {
            $this->audit->log('login.blocked', AuditCategory::Technical, $user, user: $user);

            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'This account has been deactivated. Contact your administrator.']);
        }

        if (! config('security.otp.enabled', true)) {
            return $this->completeLogin($request, $user);
        }

        $this->otp->issue($user);
        $request->session()->put(self::PendingUserKey, $user->id);
        $this->audit->log('login.otp_sent', AuditCategory::Technical, $user, user: $user);

        return redirect()->route('otp.show');
    }

    public function showOtp(Request $request): View|RedirectResponse
    {
        if (! config('security.otp.enabled', true)) {
            return redirect()->route('dashboard');
        }

        $user = $this->pendingUser($request);

        if ($user === null) {
            return redirect()->route('login');
        }

        return view('auth.otp', [
            'email' => $user->email,
            'expiresAt' => $user->otp_expires_at,
            'attemptsRemaining' => $this->otp->attemptsRemaining($user),
        ]);
    }

    public function verifyOtp(VerifyOtpRequest $request): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if ($user === null) {
            return redirect()->route('login');
        }

        $result = $this->otp->verify($user, $request->string('code'));

        return match ($result) {
            OtpResult::Verified => $this->completeLogin($request, $user),
            OtpResult::Expired => back()->withErrors(['code' => 'This code has expired. Request a new one.']),
            OtpResult::Invalid => back()->withErrors([
                'code' => 'Incorrect code. '.$this->otp->attemptsRemaining($user).' attempt(s) remaining.',
            ]),
            OtpResult::Locked => $this->lockOut($request, $user),
        };
    }

    public function resendOtp(Request $request): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if ($user === null) {
            return redirect()->route('login');
        }

        $this->otp->issue($user);
        $this->audit->log('login.otp_resent', AuditCategory::Technical, $user, user: $user);

        return back()->with('status', 'A new code has been sent to your email.');
    }

    public function logout(Request $request): RedirectResponse
    {
        $this->audit->log('logout', AuditCategory::Technical);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function showChangePassword(): View
    {
        return view('auth.change-password');
    }

    public function changePassword(ChangePasswordRequest $request): RedirectResponse
    {
        $request->user()->forceFill([
            'password' => $request->string('password'),
            'password_changed_at' => now(),
        ])->save();

        $this->audit->log('password.changed', AuditCategory::Technical, $request->user());

        return redirect()->route('dashboard')->with('status', 'Your password has been changed.');
    }

    private function completeLogin(Request $request, User $user): RedirectResponse
    {
        $user->forceFill(['mfa_verified_at' => now()])->save();

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->forget(self::PendingUserKey);
        $request->session()->put(EnsureMfaVerified::SessionKey, true);

        $this->audit->log('login.success', AuditCategory::Technical, $user, user: $user);

        return redirect()->intended(route('dashboard'));
    }

    private function lockOut(Request $request, User $user): RedirectResponse
    {
        $request->session()->forget(self::PendingUserKey);
        $this->audit->log('mfa.locked', AuditCategory::Technical, $user, user: $user);

        return redirect()->route('login')->withErrors([
            'email' => 'Too many incorrect codes. Please sign in again.',
        ]);
    }

    private function pendingUser(Request $request): ?User
    {
        $id = $request->session()->get(self::PendingUserKey);

        return $id === null ? null : User::query()->where('is_active', true)->find($id);
    }
}
