<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Models\Term;
use App\Models\User;
use App\Services\Grades\GradeService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** F-308: teacher-class-subject-term scope, revocation and re-grant. */
class TeacherAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $teacher;

    private SchoolClass $class;

    private Subject $subject;

    private Term $term;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $this->teacher = User::factory()->teacher()->create();
        $this->class = SchoolClass::factory()->create();
        $this->subject = Subject::factory()->create();
        $this->term = Term::factory()->create(['academic_year_id' => $this->class->academic_year_id]);
    }

    /** @return array<string, int> */
    private function payload(array $override = []): array
    {
        return [...['teacher_id' => $this->teacher->id, 'school_class_id' => $this->class->id, 'subject_id' => $this->subject->id, 'term_id' => $this->term->id], ...$override];
    }

    private function assignment(array $attributes = []): TeacherAssignment
    {
        return TeacherAssignment::factory()->create([...$this->payload(), ...$attributes]);
    }

    public function test_index_is_an_allowlisted_projection_with_active_filter_default(): void
    {
        $active = $this->assignment();
        $this->assignment(['teacher_id' => User::factory()->teacher()->create()->id, 'revoked_at' => now()]);

        $this->actingAs($this->admin)->get(route('admin.assignments.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin/assignments/index')
                ->has('assignments.data', 1)
                ->where('assignments.data.0.id', (string) $active->id)
                ->where('assignments.data.0.revokedAt', null)
                ->has('assignments.data.0', fn (Assert $row) => $row->hasAll(['id', 'teacher', 'schoolClass', 'subject', 'term', 'revokedAt']))
                ->has('assignments.data.0.teacher', fn (Assert $o) => $o->hasAll(['id', 'label']))
                ->where('filters.revoked', false)
                ->has('createOptions.teachers', 2)->has('createOptions.classes', 1)->has('createOptions.subjects', 1)->has('createOptions.terms', 1)
                ->missing('createOptions.teachers.0.email')->missing('filterOptions.teachers.0.email'));

        $this->actingAs($this->admin)->get(route('admin.assignments.index', ['revoked' => 1]))
            ->assertInertia(fn (Assert $page) => $page->has('assignments.data', 1)->where('assignments.data.0.revokedAt', fn ($v) => is_string($v)));
    }

    /** @return array<string, array{string}> */
    public static function historicalTeachers(): array
    {
        return ['inactive' => ['inactive'], 'deleted' => ['deleted']];
    }

    #[DataProvider('historicalTeachers')]
    public function test_historical_filter_options_remain_labelled_and_cannot_be_used_for_writes(string $state): void
    {
        $assignment = $this->assignment(['revoked_at' => now()]);
        $this->teacher->update(['status' => 'inactive']);
        if ($state === 'deleted') {
            $this->teacher->delete();
        }
        $this->class->delete();
        $this->subject->delete();
        $this->term->update(['closed_at' => now()]);
        // Unrelated historical records must not consume the filter dictionaries.
        User::factory()->teacher()->create(['status' => 'inactive']);
        SchoolClass::factory()->create()->delete();
        Subject::factory()->create()->delete();
        Term::factory()->create(['closed_at' => now()]);
        $labels = [
            'teachers' => $this->teacher->first_name.' '.$this->teacher->last_name.($state === 'deleted' ? ' (usunięty)' : ' (nieaktywny)'),
            'classes' => $this->class->name.' · '.$this->class->academicYear->name.' (zarchiwizowana)',
            'subjects' => $this->subject->name.' (zarchiwizowany)',
            'terms' => 'Semestr '.$this->term->number.' · '.$this->term->academicYear->name.' (zamknięty)',
        ];
        $url = route('admin.assignments.index', [...$this->payload(), 'revoked' => 1]);
        // First visit and reload reconstruct both the selected IDs and their labels.
        foreach ([$url, $url, route('admin.assignments.index')] as $index => $visit) {
            $this->actingAs($this->admin)->get($visit)->assertOk()->assertInertia(function (Assert $page) use ($assignment, $labels, $index) {
                $page->has('assignments.data', $index < 2 ? 1 : 0);
                if ($index < 2) {
                    $page->where('assignments.data.0.id', (string) $assignment->id)
                        ->where('filters.teacherId', (string) $this->teacher->id)
                        ->where('filters.schoolClassId', (string) $this->class->id)
                        ->where('filters.subjectId', (string) $this->subject->id)
                        ->where('filters.termId', (string) $this->term->id);
                }
                foreach ($labels as $key => $label) {
                    $page->has('filterOptions.'.$key, 1)
                        ->has('filterOptions.'.$key.'.0', fn (Assert $option) => $option->hasAll(['id', 'label']))
                        ->where('filterOptions.'.$key.'.0.label', $label)
                        ->has('createOptions.'.$key, 0);
                }
            });
        }
        $this->post(route('admin.assignments.store'), $this->payload())->assertSessionHasErrors();
        $this->patch(route('admin.assignments.update', $assignment), ['revoked' => false])->assertSessionHasErrors();
        $this->assertNotNull($assignment->fresh()->revoked_at);
        $this->assertSame(1, TeacherAssignment::count());
    }

    /** @return array<string, array{string, string}> */
    public static function invalidReactivations(): array
    {
        return [
            'inactive teacher' => ['inactive', 'teacher_id'],
            'deleted teacher' => ['deleted', 'teacher_id'],
            'archived class' => ['class', 'school_class_id'],
            'archived subject' => ['subject', 'subject_id'],
            'closed term' => ['closed', 'term_id'],
        ];
    }

    #[DataProvider('invalidReactivations')]
    public function test_reactivation_rejects_each_historical_context(string $state, string $error): void
    {
        $assignment = $this->assignment(['revoked_at' => now()]);
        match ($state) {
            'inactive' => $this->teacher->update(['status' => 'inactive']),
            'deleted' => $this->teacher->delete(),
            'class' => $this->class->delete(),
            'subject' => $this->subject->delete(),
            'closed' => $this->term->update(['closed_at' => now()]),
        };
        $this->actingAs($this->admin)->patch(route('admin.assignments.update', $assignment), ['revoked' => false])
            ->assertSessionHasErrors($error);
        $this->assertNotNull($assignment->fresh()->revoked_at);
    }

    public function test_filters_narrow_by_every_dimension(): void
    {
        $this->assignment();
        $other = TeacherAssignment::factory()->create();

        $this->actingAs($this->admin)->get(route('admin.assignments.index', ['teacher_id' => $other->teacher_id]))
            ->assertInertia(fn (Assert $page) => $page->has('assignments.data', 1)->where('assignments.data.0.id', (string) $other->id));
        $this->get(route('admin.assignments.index', ['school_class_id' => $this->class->id, 'subject_id' => $this->subject->id, 'term_id' => $this->term->id]))
            ->assertInertia(fn (Assert $page) => $page->has('assignments.data', 1));
    }

    public function test_invalid_filters_redirect_303_without_the_bad_parameter_and_out_of_range_page_redirects(): void
    {
        $this->actingAs($this->admin)->get(route('admin.assignments.index', ['subject_id' => 5, 'per_page' => 7, 'sort' => 'name']))
            ->assertStatus(303)->assertRedirect(route('admin.assignments.index', ['subject_id' => 5]))->assertSessionHasErrors(['per_page', 'sort']);

        TeacherAssignment::factory()->count(12)->create();
        $this->get(route('admin.assignments.index', ['page' => 9]))->assertRedirect(route('admin.assignments.index', ['page' => 2]));
    }

    public function test_admin_grants_a_scope_and_reaches_the_teacher_context(): void
    {
        $this->actingAs($this->admin)->post(route('admin.assignments.store'), $this->payload() + ['revoked_at' => null, 'id' => 99])
            ->assertRedirect(route('admin.assignments.index'))->assertSessionHasNoErrors();

        $assignment = TeacherAssignment::firstOrFail();
        $this->assertNotSame(99, $assignment->id);
        $this->assertNull($assignment->revoked_at);
        $this->assertTrue(TeacherAssignment::visibleTo($this->teacher)->whereKey($assignment->id)->exists());
    }

    public function test_co_teaching_is_allowed_and_regrant_is_idempotent(): void
    {
        $second = User::factory()->teacher()->create();
        $this->actingAs($this->admin)->post(route('admin.assignments.store'), $this->payload());
        $this->post(route('admin.assignments.store'), $this->payload(['teacher_id' => $second->id]))->assertSessionHasNoErrors();
        $this->post(route('admin.assignments.store'), $this->payload())->assertSessionHasNoErrors();

        $this->assertSame(2, TeacherAssignment::count());
    }

    public function test_regrant_of_a_revoked_quadruple_reactivates_the_same_row(): void
    {
        $assignment = $this->assignment(['revoked_at' => now()]);
        $this->actingAs($this->admin)->post(route('admin.assignments.store'), $this->payload())->assertSessionHasNoErrors();

        $this->assertSame(1, TeacherAssignment::count());
        $this->assertNull($assignment->fresh()->revoked_at);
    }

    /** @return array<string, array{string}> */
    public static function invalidScopes(): array
    {
        return ['student as teacher' => ['student'], 'admin as teacher' => ['admin'], 'inactive teacher' => ['inactive'], 'deleted teacher' => ['deleted'],
            'foreign year term' => ['term'], 'closed term' => ['closed'], 'archived class' => ['class'], 'archived subject' => ['subject'], 'missing' => ['missing']];
    }

    #[DataProvider('invalidScopes')]
    public function test_invalid_scope_is_rejected_without_writing(string $case): void
    {
        $override = match ($case) {
            'student' => ['teacher_id' => User::factory()->create()->id],
            'admin' => ['teacher_id' => $this->admin->id],
            'inactive' => ['teacher_id' => User::factory()->teacher()->create(['status' => 'inactive'])->id],
            'deleted' => ['teacher_id' => tap(User::factory()->teacher()->create())->delete()->id],
            'term' => ['term_id' => Term::factory()->create(['academic_year_id' => AcademicYear::factory()->create()->id])->id],
            'closed' => ['term_id' => Term::factory()->create(['academic_year_id' => $this->class->academic_year_id, 'number' => 2, 'closed_at' => now()])->id],
            'class' => ['school_class_id' => tap(SchoolClass::factory()->create())->delete()->id],
            'subject' => ['subject_id' => tap(Subject::factory()->create())->delete()->id],
            'missing' => ['teacher_id' => 999999],
        };

        $this->actingAs($this->admin)->from(route('admin.assignments.index'))->post(route('admin.assignments.store'), $this->payload($override))
            ->assertRedirect(route('admin.assignments.index'))->assertSessionHasErrors();
        $this->assertSame(0, TeacherAssignment::count());
    }

    public function test_required_fields_and_non_integer_values_fail_validation(): void
    {
        $this->actingAs($this->admin)->post(route('admin.assignments.store'), ['teacher_id' => ['x'], 'subject_id' => 'abc'])
            ->assertSessionHasErrors(['teacher_id', 'school_class_id', 'subject_id', 'term_id']);
        $this->assertSame(0, TeacherAssignment::count());
    }

    public function test_revoke_via_delete_requires_confirmation_keeps_the_row_and_is_idempotent(): void
    {
        $assignment = $this->assignment();
        $this->actingAs($this->admin)->delete(route('admin.assignments.destroy', $assignment), [])->assertSessionHasErrors('confirmed');
        $this->assertNull($assignment->fresh()->revoked_at);

        $this->delete(route('admin.assignments.destroy', $assignment), ['confirmed' => true])->assertRedirect(route('admin.assignments.index'));
        $revokedAt = $assignment->fresh()->revoked_at;
        $this->assertNotNull($revokedAt);
        $this->delete(route('admin.assignments.destroy', $assignment), ['confirmed' => true])->assertSessionHasNoErrors();
        $this->assertEquals($revokedAt, $assignment->fresh()->revoked_at);
        $this->assertSame(1, TeacherAssignment::count());
    }

    public function test_update_toggles_revoked_and_never_changes_identity(): void
    {
        $assignment = $this->assignment();
        $other = User::factory()->teacher()->create();
        $this->actingAs($this->admin)->patch(route('admin.assignments.update', $assignment), ['revoked' => true, 'teacher_id' => $other->id, 'subject_id' => 1])->assertSessionHasNoErrors();
        $this->assertNotNull($assignment->fresh()->revoked_at);
        $this->assertSame($this->teacher->id, $assignment->fresh()->teacher_id);

        $this->patch(route('admin.assignments.update', $assignment), ['revoked' => false])->assertSessionHasNoErrors();
        $this->assertNull($assignment->fresh()->revoked_at);
        $this->patch(route('admin.assignments.update', $assignment), [])->assertSessionHasErrors('revoked');
    }

    public function test_reactivation_rechecks_teacher_and_term(): void
    {
        $assignment = $this->assignment(['revoked_at' => now()]);
        $this->teacher->update(['status' => 'inactive']);
        $this->actingAs($this->admin)->from(route('admin.assignments.index'))->patch(route('admin.assignments.update', $assignment), ['revoked' => false])->assertSessionHasErrors('teacher_id');
        $this->assertNotNull($assignment->fresh()->revoked_at);

        $this->teacher->update(['status' => 'active']);
        $this->term->update(['closed_at' => now()]);
        $this->patch(route('admin.assignments.update', $assignment), ['revoked' => false])->assertSessionHasErrors('term_id');
        $this->assertNotNull($assignment->fresh()->revoked_at);
    }

    public function test_revocation_is_allowed_in_a_closed_term(): void
    {
        $assignment = $this->assignment();
        $this->term->update(['closed_at' => now()]);
        $this->actingAs($this->admin)->delete(route('admin.assignments.destroy', $assignment), ['confirmed' => true])->assertSessionHasNoErrors();
        $this->assertNotNull($assignment->fresh()->revoked_at);
    }

    public function test_missing_assignment_is_404(): void
    {
        $this->actingAs($this->admin)->patch(route('admin.assignments.update', 999999), ['revoked' => true])->assertNotFound();
        $this->delete(route('admin.assignments.destroy', 999999), ['confirmed' => true])->assertNotFound();
    }

    /** @return array<string, array{string}> */
    public static function nonAdmins(): array
    {
        return ['student' => ['student'], 'teacher' => ['teacher']];
    }

    #[DataProvider('nonAdmins')]
    public function test_other_roles_are_forbidden_and_nothing_changes(string $role): void
    {
        $assignment = $this->assignment();
        $actor = $this->actingAs(User::factory()->create(['role' => $role]));

        $actor->get(route('admin.assignments.index'))->assertForbidden();
        $actor->post(route('admin.assignments.store'), $this->payload(['teacher_id' => $this->teacher->id]))->assertForbidden();
        $actor->patch(route('admin.assignments.update', $assignment), ['revoked' => true])->assertForbidden();
        $actor->delete(route('admin.assignments.destroy', $assignment), ['confirmed' => true])->assertForbidden();
        $this->assertNull($assignment->fresh()->revoked_at);
        $this->assertSame(1, TeacherAssignment::count());
    }

    public function test_the_assigned_teacher_cannot_revoke_their_own_scope(): void
    {
        $assignment = $this->assignment();
        $this->actingAs($this->teacher)->delete(route('admin.assignments.destroy', $assignment), ['confirmed' => true])->assertForbidden();
        $this->assertNull($assignment->fresh()->revoked_at);
    }

    public function test_guests_and_inactive_admins_get_no_access(): void
    {
        $assignment = $this->assignment();
        $this->get(route('admin.assignments.index'))->assertRedirect(route('login'));
        $this->post(route('admin.assignments.store'), $this->payload())->assertRedirect(route('login'));
        $this->actingAs(User::factory()->admin()->create(['status' => 'inactive']))
            ->delete(route('admin.assignments.destroy', $assignment), ['confirmed' => true])->assertRedirect(route('login'));
        $this->assertNull($assignment->fresh()->revoked_at);
    }

    public function test_revocation_removes_read_write_and_history_while_the_co_teacher_keeps_access(): void
    {
        $coTeacher = User::factory()->teacher()->create();
        $revoked = $this->assignment();
        $kept = $this->assignment(['teacher_id' => $coTeacher->id]);
        $assessment = Assessment::factory()->create(['school_class_id' => $this->class->id, 'subject_id' => $this->subject->id, 'term_id' => $this->term->id]);
        $grade = Grade::factory()->create(['assessment_id' => $assessment->id]);

        $this->actingAs($this->admin)->delete(route('admin.assignments.destroy', $revoked), ['confirmed' => true])->assertSessionHasNoErrors();

        $this->assertFalse(Grade::visibleTo($this->teacher)->whereKey($grade->id)->exists());
        $this->assertTrue(Grade::visibleTo($coTeacher)->whereKey($grade->id)->exists());
        $this->assertFalse(TeacherAssignment::visibleTo($this->teacher)->exists());
        $this->assertTrue(TeacherAssignment::visibleTo($coTeacher)->whereKey($kept->id)->exists());
        $this->expectException(AuthorizationException::class);
        app(GradeService::class)->update($this->teacher->id, $grade->id, $grade->version, 'Po cofnięciu', ['value' => 6]);
    }

    public function test_admin_navigation_lists_assignments(): void
    {
        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('navigation.primary.3.key', 'assignments')
                ->where('navigation.primary.3.href', route('admin.assignments.index', absolute: false)));
    }
}
