<?php

namespace Tests\Feature\Authorization;

use App\Models\Assessment;
use App\Models\Grade;
use App\Models\GradeAudit;
use App\Models\SchoolClass;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Services\Authorization\GradeAccess;
use App\Services\Grades\GradeService;
use App\Services\Grades\GradeSummary;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RecordAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.password' => 'only-in-test-fixture', 'demo.date' => '2026-11-15']);
        $this->seed();

        // HTTP probes exist only in this test; no feature routes are published by F-005.
        Route::middleware(['web', 'auth', 'role:teacher'])->get('/_test/teacher', fn () => response()->noContent());
        Route::middleware(['web', 'auth'])->patch('/_test/grades/{grade}', function (int $grade) {
            app(GradeService::class)->update(request()->user()->id, $grade, 1, 'Korekta wyniku', ['value' => 6]);

            return response()->noContent();
        });
        Route::middleware(['web', 'auth', 'role:student,teacher,admin'])->get('/_test/grades/{grade}', function (int $grade) {
            $record = app(GradeAccess::class)->grade(request()->user(), $grade, true);

            return response()->json(['id' => (string) $record->id]);
        });
    }

    private function actor(string $name): User
    {
        return User::where('email', $name.'@example.test')->firstOrFail();
    }

    private function grade(): Grade
    {
        return Grade::whereHas('enrollment.student', fn ($query) => $query->where('email', 'student7@example.test'))
            ->whereHas('assessment.term.academicYear', fn ($query) => $query->where('name', '2026/2027'))
            ->orderBy('id')->firstOrFail();
    }

    public static function readers(): array
    {
        return [
            'owner' => ['student7', true, false],
            'other student' => ['student8', false, false],
            'assigned teacher' => ['teacher1', true, true],
            'other subject' => ['teacher2', false, false],
            'unassigned teacher' => ['teacher3', false, false],
            'admin' => ['admin1', true, true],
            'inactive' => ['student17', false, false],
        ];
    }

    #[DataProvider('readers')]
    public function test_policy_scope_history_count_summary_and_pagination_agree(string $name, bool $read, bool $write): void
    {
        $actor = $this->actor($name);
        $grade = $this->grade();
        $gate = Gate::forUser($actor);
        foreach (['view', 'viewHistory'] as $ability) {
            $this->assertSame($read, $gate->allows($ability, $grade));
        }
        foreach (['update', 'delete'] as $ability) {
            $this->assertSame($write, $gate->allows($ability, $grade));
        }
        $this->assertSame($write, $gate->allows('create', [Grade::class, $grade->assessment, $grade->enrollment]));
        $query = Grade::visibleTo($actor)->where('assessment_id', $grade->assessment_id)->where('student_enrollment_id', $grade->student_enrollment_id);
        $this->assertSame($read ? 1 : 0, $query->count());
        $this->assertSame($read ? 1 : 0, $query->paginate(1)->total());
        $this->assertSame($read ? 1 : 0, GradeAudit::visibleTo($actor)->where('grade_id', $grade->id)->count());
        $this->assertSame($read, $gate->allows('view', $grade->audits()->firstOrFail()));
        $summary = app(GradeSummary::class)->forStudent($actor, $grade->enrollment->student_id, $grade->assessment->term_id);
        $this->assertSame($read ? ['average' => '4.43', 'count' => 4] : ['average' => null, 'count' => 0], $summary);
    }

    public function test_role_middleware_has_no_admin_bypass_and_guest_redirects(): void
    {
        $this->get('/_test/teacher')->assertRedirect(route('login'));
        foreach (['student7', 'admin1'] as $name) {
            $this->actingAs($this->actor($name))->get('/_test/teacher')->assertForbidden();
        }
        $this->actingAs($this->actor('teacher1'))->get('/_test/teacher')->assertNoContent();
        $this->actingAs($this->actor('student17'))->get('/_test/teacher')->assertRedirect(route('login'));
    }

    public function test_student_swapping_grade_id_gets_the_same_404_as_missing_record(): void
    {
        $grade = $this->grade();
        $this->actingAs($this->actor('student8'));
        $response = $this->getJson('/_test/grades/'.$grade->id)->assertNotFound();
        $response->assertDontSee($grade->topic)->assertDontSee($grade->enrollment->student->email);
        $this->getJson('/_test/grades/999999999')->assertNotFound();
        $this->actingAs($this->actor('student7'))->getJson('/_test/grades/'.$grade->id)->assertOk()->assertExactJson(['id' => (string) $grade->id]);
    }

    public function test_direct_mutation_rechecks_record_access_and_ignores_forged_capabilities(): void
    {
        $grade = $this->grade();
        $before = GradeAudit::count();
        foreach (['student8', 'teacher2', 'teacher3'] as $name) {
            $this->actingAs($this->actor($name))->patchJson('/_test/grades/'.$grade->id, [
                'can' => ['update' => true], 'role' => 'admin', 'issued_by' => $this->actor('admin1')->id,
            ])->assertNotFound()->assertDontSee($grade->topic);
        }
        $this->actingAs($this->actor('student7'))->patchJson('/_test/grades/'.$grade->id)->assertForbidden();
        $this->assertSame($before, GradeAudit::count());
        $this->assertSame(4, $grade->fresh()->value);
        $this->assertSame(1, $grade->fresh()->version);
    }

    public function test_ended_enrollment_preserves_ownership_and_historical_corrections(): void
    {
        $grade = $this->grade();
        $grade->enrollment->update(['ends_on' => '2026-11-14']);
        $owner = $this->actor('student7');
        $this->assertTrue($owner->can('viewHistory', $grade));
        $this->assertFalse($owner->can('update', $grade));
        $this->assertTrue($this->actor('teacher1')->can('update', $grade));
        $this->assertTrue($this->actor('admin1')->can('update', $grade));
        app(GradeService::class)->update($this->actor('teacher1')->id, $grade->id, 1, 'Korekta historyczna', ['value' => 5]);
        $this->assertSame(5, $grade->fresh()->value);
    }

    public function test_deleted_or_inactive_actor_has_no_scopes_or_capabilities_even_as_admin(): void
    {
        $actor = $this->actor('admin1');
        $actor->delete();
        foreach ([Grade::class, GradeAudit::class, Assessment::class, SchoolClass::class, Subject::class, StudentEnrollment::class, TeacherAssignment::class] as $model) {
            $this->assertSame(0, $model::visibleTo($actor)->count());
        }
        $this->assertFalse($actor->can('view', $this->grade()));
        $this->assertFalse($actor->can('create', Grade::class));
        $this->assertFalse($actor->can('viewAny', User::class));
        $actor = $this->actor('admin2');
        $actor->update(['status' => 'inactive']);
        $this->assertFalse($actor->can('viewHistory', $this->grade()));
        $this->assertFalse($actor->can('create', SchoolClass::class));
    }

    public function test_same_class_different_subject_or_term_does_not_grant_access(): void
    {
        $grade = $this->grade();
        $actor = $this->actor('teacher3');
        $otherSubject = Subject::where('id', '<>', $grade->assessment->subject_id)->firstOrFail();
        $assignment = TeacherAssignment::create([
            'teacher_id' => $actor->id, 'school_class_id' => $grade->assessment->school_class_id,
            'subject_id' => $otherSubject->id, 'term_id' => $grade->assessment->term_id,
        ]);
        $this->assertFalse($actor->can('view', $grade));
        $this->assertSame(0, Grade::visibleTo($actor)->whereKey($grade->id)->count());
        $assignment->update(['subject_id' => $grade->assessment->subject_id, 'term_id' => $grade->assessment->term->academicYear->terms()->where('id', '<>', $grade->assessment->term_id)->firstOrFail()->id]);
        $this->assertFalse($actor->can('viewHistory', $grade));
        $assignment->update(['term_id' => $grade->assessment->term_id, 'school_class_id' => SchoolClass::where('academic_year_id', $grade->assessment->term->academic_year_id)->where('id', '<>', $grade->assessment->school_class_id)->firstOrFail()->id]);
        $this->assertFalse($actor->can('view', $grade));
    }

    public function test_revocation_after_opening_form_denies_all_mutations_without_any_audit(): void
    {
        $grade = $this->grade();
        $actor = $this->actor('teacher1');
        $access = app(GradeAccess::class);
        $assignment = TeacherAssignment::active()->where('teacher_id', $actor->id)->where('subject_id', $grade->assessment->subject_id)->where('term_id', $grade->assessment->term_id)->firstOrFail();
        $this->assertTrue($access->capabilities($actor, $grade)['update']);
        $this->assertTrue($access->assessments($actor, $assignment)->whereKey($grade->assessment_id)->exists());
        $assignment->update(['revoked_at' => now()]);
        $before = GradeAudit::count();
        foreach (['update', 'delete', 'create'] as $action) {
            try {
                $service = app(GradeService::class);
                match ($action) {
                    'update' => $service->update($actor->id, $grade->id, 1, 'Korekta wyniku', ['value' => 6]),
                    'delete' => $service->delete($actor->id, $grade->id, 1, 'Usunięcie wyniku'),
                    'create' => $service->create($actor->id, $grade->student_enrollment_id, $grade->assessment_id, ['value' => 6]),
                };
                $this->fail('Expected denied mutation');
            } catch (AuthorizationException) {
                $this->assertSame($before, GradeAudit::count());
                $this->assertSame(4, $grade->fresh()->value);
                $this->assertSame(1, $grade->fresh()->version);
                $this->assertNull($grade->fresh()->deleted_at);
            }
        }
        $this->assertFalse($actor->can('viewHistory', $grade));
        $this->assertSame(0, $access->history($actor)->where('grade_id', $grade->id)->count());
        $this->expectException(ModelNotFoundException::class);
        $access->enrollments($actor, $assignment)->get();
    }

    public function test_history_survives_deleted_grade_and_context_but_write_is_denied_for_admin(): void
    {
        $grade = $this->grade();
        app(GradeService::class)->delete($this->actor('teacher1')->id, $grade->id, 1, 'Usunięcie wyniku');
        $grade = $grade->fresh();
        $grade->assessment->schoolClass->delete();
        $grade->assessment->subject->delete();
        foreach (['student7', 'teacher1', 'admin1'] as $name) {
            $actor = $this->actor($name);
            $this->assertTrue($actor->can('viewHistory', $grade));
            $this->assertFalse($actor->can('update', $grade));
            $this->assertCount(2, app(GradeAccess::class)->history($actor, $grade)->get());
        }
    }

    public function test_closed_term_and_inactive_student_disable_write_for_teacher_and_admin(): void
    {
        $grade = $this->grade();
        $grade->assessment->term->update(['closed_at' => now()]);
        foreach (['teacher1', 'admin1'] as $name) {
            $actor = $this->actor($name);
            $this->assertTrue($actor->can('view', $grade));
            $this->assertFalse($actor->can('update', $grade));
            $this->assertFalse($actor->can('create', [Grade::class, $grade->assessment, $grade->enrollment]));
        }
        $other = Grade::factory()->create();
        $other->enrollment->student->update(['status' => 'inactive']);
        $this->assertFalse($this->actor('admin1')->can('update', $other));
    }

    public function test_form_options_and_roster_are_scoped_to_one_authorized_context(): void
    {
        $actor = $this->actor('teacher1');
        $grade = $this->grade();
        $assignment = TeacherAssignment::active()->where('teacher_id', $actor->id)->where('subject_id', $grade->assessment->subject_id)->where('term_id', $grade->assessment->term_id)->firstOrFail();
        $access = app(GradeAccess::class);
        foreach ($access->assessments($actor, $assignment)->get() as $assessment) {
            $this->assertSame($assignment->school_class_id, $assessment->school_class_id);
            $this->assertSame($assignment->subject_id, $assessment->subject_id);
            $this->assertSame($assignment->term_id, $assessment->term_id);
        }
        $this->assertTrue($access->enrollments($actor, $assignment)->whereKey($grade->student_enrollment_id)->exists());
        $foreign = StudentEnrollment::where('school_class_id', '<>', $assignment->school_class_id)->firstOrFail();
        $this->assertFalse($access->enrollments($actor, $assignment)->whereKey($foreign->id)->exists());
        $this->assertFalse($actor->can('create', [Grade::class, $grade->assessment, $foreign]));
        $this->assertSame(0, Assessment::visibleTo($this->actor('teacher3'))->count());
        $this->assertSame(0, Subject::visibleTo($this->actor('teacher3'))->count());
        $this->assertSame(0, SchoolClass::visibleTo($this->actor('teacher3'))->count());
        $this->assertSame(0, StudentEnrollment::visibleTo($this->actor('teacher3'))->count());
        $this->expectException(ModelNotFoundException::class);
        $access->assessments($this->actor('teacher2'), $assignment)->get();
    }

    public static function roles(): array
    {
        return [['student7', false, false], ['teacher1', true, false], ['admin1', true, true]];
    }

    #[DataProvider('roles')]
    public function test_shared_capabilities_and_admin_and_profile_policies(string $name, bool $gradeWriter, bool $admin): void
    {
        $actor = $this->actor($name);
        $this->actingAs($actor)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('can', ['createGrade' => $gradeWriter, 'manageUsers' => $admin, 'manageSchool' => $admin])
            ->where('auth.user.id', (string) $actor->id)->missing('auth.user.password')->missing('auth.user.remember_token'));
        $this->assertFalse($actor->can('create', Grade::class));
        $this->assertSame($admin, $actor->can('viewAny', User::class));
        $this->assertSame($admin, $actor->can('create', SchoolClass::class));
        $this->assertTrue($actor->can('updateProfile', $actor));
        $this->assertFalse($actor->can('updateProfile', $this->actor('student8')));
        $this->assertFalse($actor->can('delete', $actor));
        $audit = $this->grade()->audits()->firstOrFail();
        foreach (['create', 'update', 'delete'] as $ability) {
            $this->assertFalse($actor->can($ability, $audit));
        }
    }
}
