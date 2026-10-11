<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Mail\OtpMail;
use App\Models\AuditLog;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    public function test_valid_password_sends_otp_without_authenticating(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => UserFactory::Password])
            ->assertRedirect(route('otp.show'));

        $this->assertGuest();
        Mail::assertSent(OtpMail::class, fn (OtpMail $mail) => $mail->hasTo($user->email)
            && preg_match('/^\d{6}$/', $mail->code) === 1);
        $this->assertNotNull($user->fresh()->otp_code);
    }

    public function test_otp_is_stored_hashed(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => UserFactory::Password]);

        $code = $this->sentCode();
        $this->assertNotSame($code, $user->fresh()->otp_code);
    }

    /**
     * @return array<string, array{Role, string}>
     */
    public static function roleDashboards(): array
    {
        return [
            'admin' => [Role::Admin, 'dashboard.admin'],
            'compliance officer' => [Role::ComplianceOfficer, 'dashboard.officer'],
            'executive reviewer' => [Role::ExecutiveReviewer, 'dashboard.executive'],
        ];
    }

    #[DataProvider('roleDashboards')]
    public function test_correct_otp_completes_login_and_is_audited(Role $role, string $dashboardView): void
    {
        $user = User::factory()->create(['role' => $role]);

        $this->post('/login', ['email' => $user->email, 'password' => UserFactory::Password]);
        $this->post('/otp', ['code' => $this->sentCode()])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->fresh()->otp_code);
        $this->assertNotNull($user->fresh()->mfa_verified_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'login.success', 'user_id' => $user->id, 'category' => 'technical']);
        $this->get('/dashboard')->assertOk()->assertViewIs($dashboardView);
    }

    public function test_wrong_password_is_rejected_and_audited(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'Wrong-Password-1'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        Mail::assertNothingSent();
        $this->assertDatabaseHas('audit_logs', ['action' => 'login.failed', 'user_id' => $user->id]);
    }

    public function test_unknown_email_failure_is_audited_without_user(): void
    {
        $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'Wrong-Password-1'])
            ->assertSessionHasErrors('email');

        $log = AuditLog::query()->where('action', 'login.failed')->sole();
        $this->assertNull($log->user_id);
        $this->assertSame('nobody@example.com', $log->metadata['email']);
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        $user = User::factory()->inactive()->create();

        $this->post('/login', ['email' => $user->email, 'password' => UserFactory::Password])
            ->assertSessionHasErrors('email');

        Mail::assertNothingSent();
        $this->assertDatabaseHas('audit_logs', ['action' => 'login.blocked', 'user_id' => $user->id]);
    }

    public function test_wrong_otp_counts_attempts(): void
    {
        $user = User::factory()->create();
        $this->post('/login', ['email' => $user->email, 'password' => UserFactory::Password]);

        $this->post('/otp', ['code' => $this->wrongCode()])->assertSessionHasErrors('code');

        $this->assertGuest();
        $this->assertSame(1, $user->fresh()->otp_attempts);
    }

    public function test_fifth_wrong_otp_locks_out(): void
    {
        $user = User::factory()->create();
        $this->post('/login', ['email' => $user->email, 'password' => UserFactory::Password]);
        $user->forceFill(['otp_attempts' => 4])->save();

        $this->post('/otp', ['code' => $this->wrongCode()])->assertRedirect(route('login'));

        $this->assertNull($user->fresh()->otp_code);
        $this->assertDatabaseHas('audit_logs', ['action' => 'mfa.locked', 'user_id' => $user->id]);
        $this->get('/otp')->assertRedirect(route('login'));
    }

    public function test_expired_otp_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->post('/login', ['email' => $user->email, 'password' => UserFactory::Password]);
        $code = $this->sentCode();

        $this->travel(6)->minutes();

        $this->post('/otp', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_otp_page_requires_pending_login(): void
    {
        $this->get('/otp')->assertRedirect(route('login'));
        $this->post('/otp', ['code' => '123456'])->assertRedirect(route('login'));
    }

    public function test_login_is_rate_limited(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 5) as $attempt) {
            $this->post('/login', ['email' => $user->email, 'password' => 'Wrong-Password-1']);
        }

        $this->post('/login', ['email' => $user->email, 'password' => UserFactory::Password])
            ->assertTooManyRequests();
    }

    public function test_authenticated_session_without_otp_cannot_reach_protected_routes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_guest_is_redirected_to_login_with_notice(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'))->assertSessionHas('status');
    }

    public function test_logout_ends_session(): void
    {
        $user = User::factory()->create();

        $this->signIn($user)->post('/logout')->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['action' => 'logout', 'user_id' => $user->id]);
    }

    public function test_session_idle_lifetime_is_fifteen_minutes(): void
    {
        $this->assertSame(15, (int) config('session.lifetime'));
        $this->assertFalse((bool) config('session.expire_on_close'));
    }

    public function test_login_directly_authenticates_when_otp_is_disabled(): void
    {
        config(['security.otp.enabled' => false]);

        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => UserFactory::Password])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        Mail::assertNothingSent();
        $this->get('/dashboard')->assertOk();
    }

    private function sentCode(): string
    {
        $code = null;
        Mail::assertSent(OtpMail::class, function (OtpMail $mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        return $code;
    }

    private function wrongCode(): string
    {
        return $this->sentCode() === '000000' ? '111111' : '000000';
    }
}
