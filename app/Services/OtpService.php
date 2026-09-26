<?php

namespace App\Services;

use App\Enums\OtpResult;
use App\Mail\OtpMail;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * Email one-time passcodes for MFA (FR-02, SEC-02).
 */
class OtpService
{
    /**
     * Generate a new code, store its hash and email it to the user.
     */
    public function issue(User $user): void
    {
        $length = config('security.otp.length');
        $code = str_pad((string) random_int(0, 10 ** $length - 1), $length, '0', STR_PAD_LEFT);

        $user->forceFill([
            'otp_code' => Hash::make($code),
            'otp_expires_at' => now()->addMinutes(config('security.otp.ttl_minutes')),
            'otp_attempts' => 0,
        ])->save();

        Mail::to($user)->send(new OtpMail($code, config('security.otp.ttl_minutes')));
    }

    public function verify(User $user, string $code): OtpResult
    {
        if ($user->otp_code === null || $this->attemptsRemaining($user) === 0) {
            $this->clear($user);

            return OtpResult::Locked;
        }

        if ($user->otp_expires_at === null || $user->otp_expires_at->isPast()) {
            return OtpResult::Expired;
        }

        if (! Hash::check($code, $user->otp_code)) {
            $user->increment('otp_attempts');

            if ($this->attemptsRemaining($user) === 0) {
                $this->clear($user);

                return OtpResult::Locked;
            }

            return OtpResult::Invalid;
        }

        $this->clear($user);
        $user->forceFill(['mfa_verified_at' => now()])->save();

        return OtpResult::Verified;
    }

    public function attemptsRemaining(User $user): int
    {
        return max(0, config('security.otp.max_attempts') - $user->otp_attempts);
    }

    private function clear(User $user): void
    {
        $user->forceFill([
            'otp_code' => null,
            'otp_expires_at' => null,
            'otp_attempts' => 0,
        ])->save();
    }
}
