<?php

namespace Tests\Feature\Admin;

use App\Models\Grade;
use App\Models\StudentEnrollment;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Services\Users\UserService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserEditingTest extends TestCase
{
    use RefreshDatabase;

    private function payload(User $user, array $changes = []): array
    {
        return [...$user->only(['first_name', 'last_name', 'email', 'role', 'status']), ...$changes];
    }

    private function targetSession(User $user): void
    {
        DB::table('sessions')->insert(['id' => 'session-'.$user->id, 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
    }

    public function test_edit_props_are_allowlisted_and_blank_password_preserves_hash_and_sessions(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();
        $this->targetSession($target);
        $hash = $target->password;
        $this->actingAs($admin)->get(route('admin.users.edit', $target))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin/users/edit')->where('user.id', (string) $target->id)
                ->missing('user.password')->missing('user.remember_token')->where('options.classes', []));
        $this->patch(route('admin.users.update', $target), $this->payload($target, ['first_name' => 'Żaneta', 'password' => '', 'password_confirmation' => '']))
            ->assertRedirect(route('admin.users.show', $target));
        $this->assertSame($hash, $target->fresh()->password);
        $this->assertSame('Żaneta', $target->fresh()->first_name);
        $this->assertDatabaseHas('sessions', ['user_id' => $target->id]);
    }

    public function test_status_role_and_password_changes_revoke_only_target_sessions(): void
    {
        $admin = User::factory()->admin()->create();
        $this->targetSession($admin);
        foreach ([['status' => 'inactive'], ['role' => 'teacher'], ['password' => 'New-Strong-Password1', 'password_confirmation' => 'New-Strong-Password1']] as $changes) {
            $target = User::factory()->create(['remember_token' => 'old-token']);
            $this->targetSession($target);
            $this->actingAs($admin)->patch(route('admin.users.update', $target), $this->payload($target, $changes))->assertSessionHasNoErrors()->assertRedirect();
            $this->assertDatabaseMissing('sessions', ['user_id' => $target->id]);
            $this->assertDatabaseHas('sessions', ['user_id' => $admin->id]);
            $this->assertNotSame('old-token', $target->fresh()->remember_token);
            if (isset($changes['password'])) {
                $this->assertTrue(Hash::check($changes['password'], $target->fresh()->password));
            }
        }
    }

    public function test_last_active_admin_cannot_be_deactivated_or_demoted_even_with_inactive_and_deleted_admins(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->admin()->create(['status' => 'inactive']);
        User::factory()->admin()->create()->delete();
        $this->targetSession($admin);
        foreach ([['status' => 'inactive'], ['role' => 'student']] as $change) {
            $this->actingAs($admin)->patch(route('admin.users.update', $admin), $this->payload($admin, $change))->assertSessionHasErrors('status');
            $this->assertSame('active', $admin->fresh()->status);
            $this->assertSame('admin', $admin->fresh()->role);
            $this->assertDatabaseHas('sessions', ['user_id' => $admin->id]);
        }
    }

    public function test_self_edit_revokes_current_authentication_after_sensitive_changes(): void
    {
        foreach ([['status' => 'inactive'], ['role' => 'teacher'], ['password' => 'New-Strong-Password1', 'password_confirmation' => 'New-Strong-Password1']] as $changes) {
            $admin = User::factory()->admin()->create();
            User::factory()->admin()->create();
            $this->actingAs($admin)->patch(route('admin.users.update', $admin), $this->payload($admin, $changes))->assertRedirect(route('login'));
            $this->assertGuest();
        }
    }

    public function test_historical_relations_block_role_changes_but_allow_account_updates(): void
    {
        $admin = User::factory()->admin()->create();
        $enrollment = StudentEnrollment::factory()->create(['ends_on' => '2026-10-01']);
        $assignment = TeacherAssignment::factory()->create(['revoked_at' => now()]);
        foreach ([$enrollment->student, $assignment->teacher] as $target) {
            $original = $target->role;
            $this->actingAs($admin)->patch(route('admin.users.update', $target), $this->payload($target, ['role' => 'admin', 'first_name' => 'Zmiana']))->assertSessionHasErrors('role');
            $this->assertSame($original, $target->fresh()->role);
            $this->assertNotSame('Zmiana', $target->fresh()->first_name);
            $this->patch(route('admin.users.update', $target), $this->payload($target, ['status' => 'inactive']))->assertSessionHasNoErrors();
        }
        $this->assertDatabaseHas('student_enrollments', ['id' => $enrollment->id]);
        $this->assertDatabaseHas('teacher_assignments', ['id' => $assignment->id]);
    }

    public function test_unique_email_including_deleted_accounts_and_field_validation_are_atomic(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();
        $taken = User::factory()->create();
        $taken->delete();
        $before = $target->fresh()->getAttributes();
        $cases = [
            ['email' => strtoupper($taken->email)], ['email' => 'invalid'], ['first_name' => '123'], ['last_name' => str_repeat('a', 81)],
            ['role' => 'superadmin'], ['status' => 'deleted'], ['password' => 'short'],
            ['password' => 'New-Strong-Password1', 'password_confirmation' => 'different'],
            ['enrollment' => ['school_class_id' => 1]], ['assignments' => [['term_id' => 1]]],
        ];
        foreach ($cases as $changes) {
            $this->actingAs($admin)->patch(route('admin.users.update', $target), $this->payload($target, $changes))->assertSessionHasErrors(array_key_first($changes));
            $this->assertSame($before, $target->fresh()->getAttributes());
        }
        $this->patch(route('admin.users.update', $target), $this->payload($target, ['email' => strtoupper($target->email)]))->assertSessionHasNoErrors();
    }

    public function test_non_admins_guests_and_deleted_targets_cannot_be_edited(): void
    {
        $target = User::factory()->create();
        foreach ([User::factory()->create(), User::factory()->teacher()->create()] as $actor) {
            $this->actingAs($actor)->get(route('admin.users.edit', $target))->assertForbidden();
            $this->patch(route('admin.users.update', $target), $this->payload($target, ['role' => 'admin']))->assertForbidden();
        }
        auth()->logout();
        $this->get(route('admin.users.edit', $target))->assertRedirect(route('login'));
        $this->patch(route('admin.users.update', $target), $this->payload($target))->assertRedirect(route('login'));
        $target->delete();
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.users.edit', $target))->assertNotFound();
        $this->patch(route('admin.users.update', $target), $this->payload($target))->assertNotFound();
    }

    public function test_database_unique_conflict_rolls_back_the_entire_account_edit(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();
        $taken = User::factory()->create();
        $this->targetSession($target);
        $before = $target->fresh()->getAttributes();
        try {
            // Direct service call simulates a unique conflict after request validation.
            app(UserService::class)->update($admin->id, $target->id, $this->payload($target, ['email' => $taken->email, 'status' => 'inactive', 'password' => 'New-Strong-Password1']));
            $this->fail('Expected a unique email conflict.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('email', $exception->errors());
        }
        $this->assertSame($before, $target->fresh()->getAttributes());
        $this->assertDatabaseHas('sessions', ['user_id' => $target->id]);
    }

    public function test_failure_revoking_sessions_rolls_back_account_and_password_changes(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();
        $this->targetSession($target);
        $before = $target->fresh()->getAttributes();
        DB::listen(function (QueryExecuted $query): void {
            if (str_starts_with($query->sql, 'delete from `sessions`')) {
                throw new \RuntimeException('Injected session deletion failure.');
            }
        });
        try {
            app(UserService::class)->update($admin->id, $target->id, $this->payload($target, ['status' => 'inactive', 'password' => 'New-Strong-Password1']));
            $this->fail('Expected a revocation failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Injected session deletion failure.', $exception->getMessage());
        }
        $this->assertSame($before, $target->fresh()->getAttributes());
        $this->assertDatabaseHas('sessions', ['user_id' => $target->id]);
    }

    public function test_account_edit_preserves_existing_grades_and_audit_history(): void
    {
        $admin = User::factory()->admin()->create();
        $grade = Grade::factory()->create();
        $target = $grade->enrollment->student;
        $beforeGrade = $grade->fresh()->getAttributes();
        $beforeAudits = $grade->audits()->get()->toArray();
        $this->actingAs($admin)->patch(route('admin.users.update', $target), $this->payload($target, ['first_name' => 'Anna', 'status' => 'inactive']))->assertSessionHasNoErrors();
        $this->assertSame($beforeGrade, $grade->fresh()->getAttributes());
        $this->assertSame($beforeAudits, $grade->audits()->get()->toArray());
    }

    public function test_service_rechecks_actor_from_database_instead_of_trusting_stale_auth(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();
        $this->actingAs($admin);
        User::whereKey($admin->id)->update(['status' => 'inactive']);
        $this->expectException(AuthorizationException::class);
        app(UserService::class)->update($admin->id, $target->id, $this->payload($target, ['role' => 'admin']));
    }
}
