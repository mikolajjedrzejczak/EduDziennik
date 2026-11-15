<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Navigation\NavigationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** F-007: role redirect, role-gated empty dashboards and server-built navigation. */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string, string}> */
    public static function roles(): array
    {
        return [
            'student' => ['student', 'student.dashboard'],
            'teacher' => ['teacher', 'teacher.dashboard'],
            'admin' => ['admin', 'admin.dashboard'],
        ];
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        foreach (['dashboard', 'student.dashboard', 'teacher.dashboard', 'admin.dashboard'] as $name) {
            $this->get(route($name))->assertRedirect(route('login'));
        }
    }

    #[DataProvider('roles')]
    public function test_dashboard_redirects_each_role_to_its_own_dashboard(string $role, string $route): void
    {
        $this->actingAs(User::factory()->create(['role' => $role]))
            ->get(route('dashboard'))->assertRedirect(route($route));
    }

    #[DataProvider('roles')]
    public function test_login_ends_on_the_role_dashboard(string $role, string $route): void
    {
        $user = User::factory()->create(['role' => $role]);
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
        $this->get(route('dashboard'))->assertRedirect(route($route));
        $this->get(route($route))->assertOk()->assertInertia(fn (Assert $page) => $page->component($role.'/dashboard'));
    }

    #[DataProvider('roles')]
    public function test_a_role_cannot_open_other_role_dashboards(string $role, string $route): void
    {
        $actor = User::factory()->create(['role' => $role]);
        foreach (['student.dashboard', 'teacher.dashboard', 'admin.dashboard'] as $other) {
            if ($other !== $route) {
                $this->actingAs($actor)->get(route($other))->assertForbidden();
            }
        }
    }

    #[DataProvider('roles')]
    public function test_navigation_is_built_by_the_server_and_lists_only_ready_routes(string $role, string $route): void
    {
        $this->actingAs(User::factory()->create(['role' => $role]))->get(route($route))
            ->assertInertia(fn (Assert $page) => $page
                ->where('navigation.primary.0.href', route($route, absolute: false))
                ->where('navigation.primary.0.label', 'Pulpit')
                ->has('navigation.primary', match ($role) {
                    'admin' => 5,
                    'student' => 2,
                    default => 1,
                })
                ->where('navigation.secondary.0.href', route('profile.edit', absolute: false))
                ->has('navigation.secondary', 1));
    }

    public function test_navigation_is_absent_for_guests_and_unknown_roles(): void
    {
        $this->get(route('login'))->assertInertia(fn (Assert $page) => $page->where('navigation', null));

        $unknown = User::factory()->create();
        $unknown->role = 'guest'; // in memory only: the database check constraint forbids it
        $this->assertNull(app(NavigationService::class)->for($unknown));
        $this->assertNull(NavigationService::dashboardRoute($unknown));
    }

    public function test_inactive_accounts_never_reach_a_dashboard(): void
    {
        $user = User::factory()->create(['status' => 'inactive']);
        $this->actingAs($user)->get(route('student.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
