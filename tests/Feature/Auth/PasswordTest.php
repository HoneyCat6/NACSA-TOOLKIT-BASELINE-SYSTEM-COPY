<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_password_forces_change(): void
    {
        $user = User::factory()->passwordExpired()->create();

        $this->signIn($user)->get('/dashboard')->assertRedirect(route('password.change'));
    }

    public function test_admin_set_password_forces_change_on_first_login(): void
    {
        $user = User::factory()->create(['password_changed_at' => null]);

        $this->signIn($user)->get('/dashboard')->assertRedirect(route('password.change'));
    }

    public function test_password_can_be_changed(): void
    {
        $user = User::factory()->passwordExpired()->create();

        $this->signIn($user)->post('/password/change', [
            'current_password' => UserFactory::Password,
            'password' => 'Brand-New-Pass#42',
            'password_confirmation' => 'Brand-New-Pass#42',
        ])->assertRedirect(route('dashboard'));

        $user->refresh();
        $this->assertTrue(Hash::check('Brand-New-Pass#42', $user->password));
        $this->assertFalse($user->passwordHasExpired());
        $this->assertDatabaseHas('audit_logs', ['action' => 'password.changed', 'user_id' => $user->id]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function weakPasswords(): array
    {
        return [
            'too short' => ['Short-Pass1'],
            'no upper case' => ['lower-case-only-1'],
            'no lower case' => ['UPPER-CASE-ONLY-1'],
            'no digit' => ['No-Digits-Here-Ok'],
            'no symbol' => ['NoSymbolsHere123'],
        ];
    }

    #[DataProvider('weakPasswords')]
    public function test_weak_passwords_are_rejected(string $password): void
    {
        $user = User::factory()->create();

        $this->signIn($user)->post('/password/change', [
            'current_password' => UserFactory::Password,
            'password' => $password,
            'password_confirmation' => $password,
        ])->assertSessionHasErrors('password');
    }

    public function test_new_password_must_differ_from_current(): void
    {
        $user = User::factory()->create();

        $this->signIn($user)->post('/password/change', [
            'current_password' => UserFactory::Password,
            'password' => UserFactory::Password,
            'password_confirmation' => UserFactory::Password,
        ])->assertSessionHasErrors('password');
    }

    public function test_forgot_password_sends_reset_link_and_reset_updates_password(): void
    {
        Notification::fake();
        $user = User::factory()->passwordExpired()->create();

        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'Reset-Password#77',
                'password_confirmation' => 'Reset-Password#77',
            ])->assertRedirect(route('login'));

            return true;
        });

        $user->refresh();
        $this->assertTrue(Hash::check('Reset-Password#77', $user->password));
        $this->assertFalse($user->passwordHasExpired());
    }

    public function test_forgot_password_does_not_reveal_unknown_accounts(): void
    {
        Notification::fake();

        $this->post('/forgot-password', ['email' => 'nobody@example.com'])->assertSessionHas('status');

        Notification::assertNothingSent();
    }
}
