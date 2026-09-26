<?php

namespace Tests;

use App\Http\Middleware\EnsureMfaVerified;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Act as a user who has completed password + OTP sign-in.
     */
    protected function signIn(User $user): static
    {
        return $this->actingAs($user)->withSession([EnsureMfaVerified::SessionKey => true]);
    }
}
