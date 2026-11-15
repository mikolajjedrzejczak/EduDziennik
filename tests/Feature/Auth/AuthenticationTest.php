<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\Auth\LoginService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_login_screen_has_no_authenticated_data(): void
    {
        $this->get(route('login'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('auth/login')->where('auth.user', null)->where('auth.role', null));
    }

    public function test_all_three_roles_can_login_with_normalized_email_and_without_remembering(): void
    {
        foreach (['student', 'teacher', 'admin'] as $role) {
            $user = User::factory()->create(['role' => $role, 'remember_token' => null]);
            $this->withSession(['url.intended' => '/admin/users']);
            $oldId = session()->getId();
            $this->post(route('login.store'), [
                'email' => '  '.strtoupper($user->email).'  ', 'password' => 'password', 'remember' => true,
            ])->assertRedirect(route('dashboard'))->assertSessionMissing('url.intended');
            $this->assertAuthenticatedAs($user);
            $this->assertNotSame($oldId, session()->getId());
            $this->assertNotNull($user->fresh()->last_login_at);
            $this->assertNull($user->fresh()->remember_token);
            $this->post(route('logout'))->assertRedirect(route('login'));
            Auth::forgetGuards();
        }
    }

    public function test_invalid_unknown_inactive_and_deleted_accounts_are_indistinguishable(): void
    {
        $active = User::factory()->create();
        $inactive = User::factory()->create(['status' => 'inactive']);
        $deleted = User::factory()->create();
        $deleted->delete();
        foreach ([[$active->email, 'wrong'], ['missing@example.com', 'password'], [$inactive->email, 'password'], [$deleted->email, 'password']] as [$email, $password]) {
            $this->from(route('login'))->post(route('login.store'), compact('email', 'password'))
                ->assertRedirect(route('login'))->assertSessionHasErrors(['form' => LoginService::FAILURE])
                ->assertSessionMissing('_old_input.password');
            $this->assertGuest();
        }
    }

    public function test_validation_uses_redirect_errors_and_does_not_flash_passwords(): void
    {
        $this->from(route('login'))->post(route('login.store'), ['email' => ['invalid'], 'password' => 'secret'])
            ->assertRedirect(route('login'))->assertSessionHasErrors('email')->assertSessionMissing('_old_input.password');
        $this->post(route('login.store'), ['email' => 'bad', 'password' => ''])->assertSessionHasErrors(['email', 'password']);
        $this->assertGuest();
    }

    public function test_sixth_attempt_is_limited_for_fifteen_minutes_even_with_correct_password(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('form');
        }
        $this->travel(2)->minutes();
        $this->post(route('login.store'), ['email' => strtoupper($user->email), 'password' => 'password'])
            ->assertTooManyRequests()->assertHeader('Retry-After', '780')
            ->assertInertia(fn (Assert $page) => $page->component('auth/login')->where('retryAfter', 780));
        $this->assertGuest();
        $this->travel(13)->minutes();
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_limit_is_scoped_to_ip_and_email(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong']);
        }
        $other = User::factory()->create();
        $this->post(route('login.store'), ['email' => $other->email, 'password' => 'password'])->assertRedirect(route('dashboard'));
        $this->post(route('logout'));
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.1'])
            ->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('dashboard'));
    }

    public function test_success_clears_previous_account_failures(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 4; $i++) {
            $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong']);
        }
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('dashboard'));
        $this->post(route('logout'));
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('form');
        }
        $this->assertGuest();
    }

    public function test_ip_limit_blocks_rotating_emails(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $this->post(route('login.store'), ['email' => "missing{$i}@example.com", 'password' => 'wrong'])->assertSessionHasErrors('form');
        }
        $this->post(route('login.store'), ['email' => 'another@example.com', 'password' => 'wrong'])->assertTooManyRequests();
    }

    public function test_logout_invalidates_session_and_rotates_csrf_token(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->withSession(['private' => 'old']);
        $oldId = session()->getId();
        $oldToken = session()->token();
        $this->post(route('logout'))->assertRedirect(route('login'))->assertSessionMissing('private');
        $this->assertGuest();
        $this->assertNotSame($oldId, session()->getId());
        $this->assertNotSame($oldToken, session()->token());
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('logout'))->assertMethodNotAllowed();
    }

    public function test_inactive_and_deleted_existing_sessions_lose_access(): void
    {
        foreach (['inactive', 'deleted'] as $state) {
            $user = User::factory()->create();
            $this->actingAs($user);
            if ($state === 'inactive') {
                $user->update(['status' => 'inactive']);
            } else {
                $user->delete();
            }
            $this->get(route('dashboard'))->assertRedirect(route('login'));
            $this->assertGuest();
        }
    }

    public function test_deleted_account_session_is_invalidated_before_auth_redirect(): void
    {
        $user = User::factory()->create();
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
        $this->withSession(['private' => 'old']);
        $user->delete();
        Auth::forgetGuards(); // Simulate a new request loading its user from the database.
        $this->get(route('dashboard'))->assertRedirect(route('login'))->assertSessionMissing('private');
        $this->assertGuest();
    }

    public function test_shared_auth_props_are_an_allowlist(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.id', (string) $user->id)->where('auth.role', $user->role)
            ->has('auth.user', 6)->missing('auth.user.password')->missing('auth.user.remember_token')
            ->missing('auth.user.last_login_at')->missing('auth.user.status'));
    }

    public function test_login_and_logout_require_csrf_and_expired_requests_are_not_replayed(): void
    {
        // Laravel bypasses CSRF in unit tests; bind the real middleware with only that bypass disabled.
        $this->app->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        });
        $user = User::factory()->create();
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertStatus(419)
            ->assertInertia(fn (Assert $page) => $page->component('auth/login')->where('auth.user', null));
        $this->assertGuest();
        $this->actingAs($user)->post(route('logout'))->assertStatus(419);
        $this->assertAuthenticatedAs($user);
        $this->withSession(['_token' => 'valid-token'])->post(route('logout'), ['_token' => 'valid-token'])->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
