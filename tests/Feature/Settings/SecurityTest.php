<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use App\Services\Users\ProfileService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_change_rotates_hash_token_and_session_and_revokes_only_other_sessions(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($user)->get(route('profile.edit'));
        $session = session()->getId();
        foreach (['own-second' => $user->id, 'foreign' => $other->id, $session => $user->id] as $id => $userId) {
            DB::table('sessions')->insert(['id' => $id, 'user_id' => $userId, 'payload' => '', 'last_activity' => time()]);
        }
        $token = $user->remember_token;
        $this->put(route('profile.password.update'), [...$this->payload(), 'role' => 'admin', 'status' => 'inactive', 'id' => $other->id])
            ->assertSessionHasNoErrors()->assertRedirect(route('profile.edit'));
        $this->assertTrue(Hash::check('New-password-123', $user->fresh()->password));
        $this->assertNotSame($token, $user->fresh()->remember_token);
        $this->assertSame($user->role, $user->fresh()->role);
        $this->assertSame('active', $user->fresh()->status);
        $this->assertTrue(Hash::check('password', $other->fresh()->password));
        $this->assertNotSame($session, session()->getId());
        $this->assertDatabaseMissing('sessions', ['id' => 'own-second']);
        $this->assertDatabaseHas('sessions', ['id' => 'foreign']);
        $this->get(route('profile.edit'))->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    public function test_bad_current_password_confirmation_length_and_reuse_do_not_change_hash(): void
    {
        $user = User::factory()->create();
        foreach ([
            ['current_password' => 'wrong'],
            ['password_confirmation' => 'different'],
            ['password' => 'short', 'password_confirmation' => 'short'],
            ['password' => str_repeat('x', 129), 'password_confirmation' => str_repeat('x', 129)],
            ['password' => 'password', 'password_confirmation' => 'password'],
        ] as $changes) {
            $this->actingAs($user)->from(route('profile.edit'))->put(route('profile.password.update'), array_replace($this->payload(), $changes))
                ->assertSessionHasErrors()->assertRedirect(route('profile.edit'));
            $this->assertTrue(Hash::check('password', $user->fresh()->password));
            $this->assertSame($user->remember_token, $user->fresh()->remember_token);
            foreach (['password', 'current_password', 'password_confirmation'] as $field) {
                $this->assertArrayNotHasKey($field, session()->getOldInput());
            }
        }
    }

    public function test_valid_length_current_password_cannot_be_reused(): void
    {
        $user = User::factory()->create(['password' => 'Original-password-123']);
        DB::table('sessions')->insert(['id' => 'other-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
        $this->actingAs($user)->from(route('profile.edit'))->put(route('profile.password.update'), [
            'current_password' => 'Original-password-123', 'password' => 'Original-password-123', 'password_confirmation' => 'Original-password-123',
        ])->assertSessionHasErrors('password');
        $this->assertSame($user->password, $user->fresh()->password);
        $this->assertDatabaseHas('sessions', ['id' => 'other-session']);
    }

    public function test_stale_session_password_hash_is_rejected(): void
    {
        $user = User::factory()->create();
        $old = $user->password;
        $user->update(['password' => 'New-password-123']);
        $this->actingAs($user)->withSession(['password_hash_web' => $old])
            ->get(route('profile.edit'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_password_limit_is_five_per_user_and_keeps_profile_context(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        for ($i = 0; $i < 5; $i++) {
            $this->from(route('profile.edit'))->put(route('profile.password.update'), array_replace($this->payload(), ['current_password' => 'wrong']))
                ->assertSessionHasErrors('current_password');
        }
        $this->put(route('profile.password.update'), $this->payload())->assertStatus(429)
            ->assertHeader('Retry-After')->assertInertia(fn (AssertableInertia $page) => $page
            ->component('settings/profile')->where('user.id', (string) $user->id)->where('passwordRateLimited', true));
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_service_rechecks_password_and_active_account_under_lock(): void
    {
        $user = User::factory()->create();
        $service = app(ProfileService::class);
        try {
            $service->password($user->id, 'stale-password', 'New-password-123', 'current');
            $this->fail('Stale password accepted.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('current_password', $e->errors());
        }
        $this->assertSame($user->password, $user->fresh()->password);
        $user->update(['status' => 'inactive']);
        $this->expectException(AuthorizationException::class);
        $service->password($user->id, 'password', 'New-password-123', 'current');
    }

    /** @return array<string, string> */
    private function payload(): array
    {
        return ['current_password' => 'password', 'password' => 'New-password-123', 'password_confirmation' => 'New-password-123'];
    }
}
