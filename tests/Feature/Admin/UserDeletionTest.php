<?php

namespace Tests\Feature\Admin;

use App\Models\Grade;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Services\Users\UserService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_delete_preserves_student_teacher_grade_and_audit_relations_and_reserves_email(): void
    {
        $admin = User::factory()->admin()->create();
        $grade = Grade::factory()->create();
        $teacher = $grade->issuer;
        $teacher->update(['role' => 'teacher']);
        $assignment = TeacherAssignment::factory()->create(['teacher_id' => $teacher->id]);
        $student = $grade->enrollment->student;
        $before = $grade->fresh()->getAttributes();
        $audits = $grade->audits()->get()->toArray();
        $this->assertNotEmpty($audits);
        foreach ([$student, $teacher] as $target) {
            DB::table('sessions')->insert(['id' => 'session-'.$target->id, 'user_id' => $target->id, 'payload' => '', 'last_activity' => time()]);
            $token = $target->remember_token;
            $this->actingAs($admin)->delete(route('admin.users.destroy', $target), ['confirmation_last_name' => ' '.$target->last_name.' ', 'email' => 'ignored@example.test'])
                ->assertSessionHasNoErrors()->assertRedirect(route('admin.users.index'));
            $deleted = User::withTrashed()->findOrFail($target->id);
            $this->assertTrue($deleted->trashed());
            $this->assertSame('inactive', $deleted->status);
            $this->assertSame($target->email, $deleted->email);
            $this->assertNotSame($token, $deleted->remember_token);
            $this->assertDatabaseMissing('sessions', ['user_id' => $target->id]);
            $this->delete(route('admin.users.destroy', $target), ['confirmation_last_name' => $target->last_name])->assertNotFound();
            $this->get(route('admin.users.index'))->assertInertia(fn (Assert $page) => $page->where('users.data', fn ($rows) => ! collect($rows)->contains('id', (string) $target->id)));
            $this->get(route('admin.users.show', $target))->assertOk();
            $this->post(route('admin.users.store'), ['first_name' => 'Anna', 'last_name' => 'Nowak', 'email' => $target->email, 'role' => 'admin', 'status' => 'active', 'password' => 'Strong-Password-1', 'password_confirmation' => 'Strong-Password-1'])->assertSessionHasErrors('email');
        }
        $this->assertSame($before, $grade->fresh()->getAttributes());
        $this->assertSame($audits, $grade->audits()->get()->toArray());
        $this->assertSame($teacher->id, $grade->fresh()->issuer->id);
        $this->assertSame($teacher->id, $grade->audits()->first()->actor->id);
        $this->assertSame($student->id, $grade->enrollment->fresh()->student->id);
        $this->assertDatabaseHas('teacher_assignments', ['id' => $assignment->id, 'teacher_id' => $teacher->id]);
        auth()->logout();
        $this->post('/login', ['email' => $student->email, 'password' => 'password'])->assertSessionHasErrors();
        $this->assertGuest();
    }

    public function test_confirmation_is_required_exact_and_rechecked_by_service(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create(['last_name' => 'Żółkowski']);
        foreach ([null, '', 'Zolkowski', 'żółkowski', ['Żółkowski']] as $confirmation) {
            $this->actingAs($admin)->delete(route('admin.users.destroy', $target), ['confirmation_last_name' => $confirmation])->assertSessionHasErrors('confirmation_last_name');
            $this->assertNotSoftDeleted($target);
        }
        try {
            app(UserService::class)->delete($admin->id, $target->id, 'Poprzednie');
            $this->fail('Expected locked confirmation rejection.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('confirmation_last_name', $e->errors());
        }
        $this->assertNotSoftDeleted($target);
    }

    public function test_guests_other_roles_self_and_stale_actor_are_denied(): void
    {
        $target = User::factory()->create();
        $payload = ['confirmation_last_name' => $target->last_name];
        $this->delete(route('admin.users.destroy', $target), $payload)->assertRedirect(route('login'));
        foreach ([User::factory()->create(), User::factory()->teacher()->create()] as $actor) {
            $this->actingAs($actor)->delete(route('admin.users.destroy', $target), $payload)->assertForbidden();
        }
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->delete(route('admin.users.destroy', $admin), ['confirmation_last_name' => $admin->last_name])->assertForbidden();
        $this->assertNotSoftDeleted($admin);
        $this->assertNotSoftDeleted($target);
        User::whereKey($admin->id)->update(['status' => 'inactive']);
        $this->expectException(AuthorizationException::class);
        app(UserService::class)->delete($admin->id, $target->id, $target->last_name);
    }

    public function test_revocation_failure_rolls_back_status_soft_delete_and_token(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();
        DB::table('sessions')->insert(['id' => 'target', 'user_id' => $target->id, 'payload' => '', 'last_activity' => time()]);
        $before = $target->fresh()->getAttributes();
        DB::listen(function (QueryExecuted $query): void {
            if (str_starts_with($query->sql, 'delete from `sessions`')) {
                throw new \RuntimeException('Injected revocation failure.');
            }
        });
        try {
            app(UserService::class)->delete($admin->id, $target->id, $target->last_name);
            $this->fail('Expected revocation failure.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Injected revocation failure.', $e->getMessage());
        }
        $this->assertSame($before, $target->fresh()->getAttributes());
        $this->assertDatabaseHas('sessions', ['id' => 'target']);
    }
}
