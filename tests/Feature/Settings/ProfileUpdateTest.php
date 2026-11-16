<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_roles_can_view_only_allowlisted_own_profile_and_update_names(): void
    {
        foreach (['student', 'teacher', 'admin'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $email = $user->email;
            $this->actingAs($user)->get(route('profile.edit'))->assertOk()
                ->assertInertia(fn (Assert $page) => $page->component('settings/profile')
                    ->where('user.id', (string) $user->id)->has('user', 6)
                    ->missing('user.password')->missing('user.remember_token'));
            $this->patch(route('profile.update'), [
                'first_name' => '  Łukasz  ', 'last_name' => 'O’Nowak-Kowalski',
                'email' => 'hacked@example.test', 'role' => 'admin', 'status' => 'inactive', 'id' => 999,
            ])->assertSessionHasNoErrors()->assertRedirect(route('profile.edit'));
            $user->refresh();
            $this->assertSame('Łukasz', $user->first_name);
            $this->assertSame('O’Nowak-Kowalski', $user->last_name);
            $this->assertSame($email, $user->email);
            $this->assertSame($role, $user->role);
            $this->assertSame('active', $user->status);
            $this->assertNotNull($user->email_verified_at);
            $this->post(route('logout'));
        }
    }

    public function test_invalid_names_do_not_partially_save(): void
    {
        $user = User::factory()->create();
        foreach (['', '123', '<script>', str_repeat('A', 81), '---', ['invalid']] as $name) {
            $this->actingAs($user)->from(route('profile.edit'))->patch(route('profile.update'), [
                'first_name' => 'Poprawne', 'last_name' => $name,
            ])->assertSessionHasErrors('last_name')->assertRedirect(route('profile.edit'));
            $this->assertSame($user->first_name, $user->fresh()->first_name);
        }
    }

    public function test_guests_and_inactive_users_cannot_update_and_self_delete_is_absent(): void
    {
        $this->patch(route('profile.update'))->assertRedirect(route('login'));
        $this->put(route('profile.password.update'))->assertRedirect(route('login'));
        $user = User::factory()->create(['status' => 'inactive']);
        $this->actingAs($user)->patch(route('profile.update'), ['first_name' => 'Nowe', 'last_name' => 'Dane'])
            ->assertRedirect(route('login'));
        $this->assertNotSame('Nowe', $user->fresh()->first_name);
        $this->delete('/profile')->assertMethodNotAllowed();
    }
}
