<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * F-001: the adapted starter exposes no public registration and none of the
 * removed starter modules (password reset by email, 2FA, passkeys, email
 * verification, self-service account deletion).
 */
class StarterScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_example_page_is_resolved_by_the_inertia_page_resolver()
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('welcome'));
    }

    public function test_removed_starter_routes_are_not_registered()
    {
        foreach ([
            'register',
            'register.store',
            'password.request',
            'password.email',
            'password.reset',
            'password.update',
            'verification.notice',
            'verification.verify',
            'verification.send',
            'two-factor.login',
            'two-factor.enable',
            'passkey.login',
            'profile.destroy',
            'well-known.passkeys',
        ] as $name) {
            $this->assertFalse(Route::has($name), "Route [{$name}] must not be registered.");
        }
    }

    public function test_guest_cannot_register_an_account()
    {
        $this->get('/register')->assertNotFound();

        $this->post('/register', [
            'name' => 'Intruder',
            'email' => 'intruder@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'intruder@example.com']);
    }

    public function test_user_cannot_delete_their_own_account()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->delete('/settings/profile', ['password' => 'password'])
            ->assertMethodNotAllowed();

        $this->assertNotNull($user->fresh());
    }

    public function test_password_reset_by_email_is_not_available()
    {
        $this->get('/forgot-password')->assertNotFound();
        $this->post('/forgot-password', ['email' => 'someone@example.com'])->assertNotFound();
    }

    public function test_removed_password_confirmation_screen_is_not_found()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/user/confirm-password')
            ->assertNotFound();
    }
}
