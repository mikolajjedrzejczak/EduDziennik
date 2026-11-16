<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Grade;
use App\Models\GradeCategory;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Models\Term;
use App\Models\User;
use App\Services\Grades\GradeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GradeDetailTest extends TestCase
{
    use RefreshDatabase;

    private Grade $grade;

    protected function setUp(): void
    {
        parent::setUp();
        $this->grade = Grade::factory()->create(['value' => 5, 'weight' => 3, 'topic' => 'Funkcja liniowa', 'comment' => '<script>tekst</script>']);
    }

    public function test_own_details_use_allowlist_and_server_side_averages(): void
    {
        $g = $this->grade;
        $a = Assessment::factory()->create([
            'school_class_id' => $g->assessment->school_class_id, 'subject_id' => $g->assessment->subject_id,
            'term_id' => $g->assessment->term_id,
        ]);
        Grade::factory()->create(['assessment_id' => $a->id, 'student_enrollment_id' => $g->student_enrollment_id, 'value' => 3, 'weight' => 1]);
        // A different student in the same context must never influence this summary.
        Grade::factory()->create(['assessment_id' => $g->assessment_id, 'value' => 1, 'weight' => 5]);
        $response = $this->actingAs($g->enrollment->student)->get(route('student.grades.show', $g))->assertOk();
        $response->assertInertia(fn (Assert $p) => $p->component('shared/grades/show')
            ->where('grade.topic', 'Funkcja liniowa')->where('grade.comment', '<script>tekst</script>')
            ->where('summary.average', '4.50')->where('summary.count', 2)->where('averageWithoutGrade', '3.00')
            ->where('historyUrl', route('student.grades.show', $g, absolute: false).'#grade-history')
            ->where('grade.issuedBy', ['id' => (string) $g->issued_by, 'label' => $g->issuer->name])
            ->missing('grade.student.email')->missing('grade.issuer')->missing('history.data.0.old_values')
            ->where('grade.can', ['update' => false, 'delete' => false, 'viewHistory' => true]));
        $payload = $response->viewData('page')['props']['grade'];
        $this->assertEqualsCanonicalizing(['id', 'student', 'assessmentId', 'context', 'value', 'weight', 'category', 'topic', 'comment', 'gradedOn', 'issuedBy', 'createdAt', 'updatedAt', 'deletedAt', 'version', 'differsFromAssessment', 'can'], array_keys($payload));
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('admin.grades.show', $g))->assertOk();
    }

    public function test_deleted_grade_keeps_details_and_audit_but_is_excluded_from_average(): void
    {
        $g = $this->grade;
        app(GradeService::class)->delete($g->issued_by, $g->id, $g->version, 'Błędny wpis');
        $this->actingAs($g->enrollment->student)->get(route('student.grades.show', $g))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('shared/grades/show')->where('grade.value', 5)
                ->where('grade.can', ['update' => false, 'delete' => false, 'viewHistory' => true])
                ->where('summary.average', null)->where('summary.count', 0)->has('history.data', 2)
                ->where('history.data.0.action', 'delete')->where('history.data.1.action', 'create')
                ->where('history.data.0.reason', 'Błędny wpis')->where('grade.deletedAt', fn ($v) => is_string($v)));
    }

    public function test_deleted_grade_is_read_only_for_writers_too(): void
    {
        $g = $this->grade;
        $teacher = User::factory()->teacher()->create();
        TeacherAssignment::factory()->create([
            'teacher_id' => $teacher->id, 'school_class_id' => $g->assessment->school_class_id,
            'subject_id' => $g->assessment->subject_id, 'term_id' => $g->assessment->term_id,
        ]);
        app(GradeService::class)->delete($g->issued_by, $g->id, $g->version, 'Błędny wpis');
        foreach ([$teacher, User::factory()->admin()->create()] as $actor) {
            $this->actingAs($actor)->get(route($actor->role.'.grades.show', $g))->assertOk()
                ->assertInertia(fn (Assert $p) => $p->where('grade.can', ['update' => false, 'delete' => false, 'viewHistory' => true])
                    ->where('history.meta.total', 2));
        }
    }

    public function test_teacher_scope_uses_exact_assignment_and_revocation_not_authorship(): void
    {
        $g = $this->grade;
        $teacher = User::factory()->teacher()->create();
        $this->actingAs($teacher)->get(route('teacher.grades.show', $g))->assertNotFound();
        $assignment = TeacherAssignment::factory()->create([
            'teacher_id' => $teacher->id, 'school_class_id' => $g->assessment->school_class_id,
            'subject_id' => $g->assessment->subject_id, 'term_id' => $g->assessment->term_id,
        ]);
        $this->actingAs($teacher)->get(route('teacher.grades.show', $g))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('grade.can.update', true)->where('grade.can.delete', true));
        foreach (['subject_id' => Subject::factory()->create()->id, 'term_id' => Term::factory()->create()->id,
            'school_class_id' => SchoolClass::factory()->create()->id] as $field => $id) {
            $original = $assignment->$field;
            $assignment->update([$field => $id]);
            $this->actingAs($teacher)->get(route('teacher.grades.show', $g))->assertNotFound();
            $assignment->update([$field => $original]);
        }
        $assignment->update(['revoked_at' => now()]);
        $this->actingAs($teacher)->get(route('teacher.grades.show', $g))->assertNotFound();
        $g->issuer->update(['role' => 'teacher']);
        $this->actingAs($g->issuer)->get(route('teacher.grades.show', $g))->assertNotFound();
    }

    public function test_archived_context_inactive_category_and_deleted_issuer_remain_readable(): void
    {
        $g = $this->grade;
        $g->issuer->delete();
        GradeCategory::whereKey($g->category_id)->update(['is_active' => false]);
        $g->assessment->subject->delete();
        $g->assessment->schoolClass->delete();
        $g->assessment->term->update(['closed_at' => now()]);
        $g->assessment->delete();
        $g->enrollment->update(['ends_on' => $g->graded_on]);
        $this->actingAs($g->enrollment->student)->get(route('student.grades.show', $g))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('grade.issuedBy.label', $g->issuer->name)->where('grade.context.closed', true)->has('history.data', 1));
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('admin.grades.show', $g))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('grade.can.update', false)->where('grade.can.delete', false));
    }

    public function test_direct_foreign_missing_malformed_and_wrong_role_urls_do_not_leak(): void
    {
        $student = User::factory()->create();
        $this->actingAs($student)->get(route('student.grades.show', $this->grade))->assertNotFound();
        $this->get('/student/grades/999999')->assertNotFound();
        $this->get('/student/grades/bad')->assertNotFound();
        $this->get(route('teacher.grades.show', $this->grade))->assertForbidden();
        $this->get(route('admin.grades.show', $this->grade))->assertForbidden();
        $this->grade->delete();
        $this->get(route('student.grades.show', $this->grade))->assertNotFound();
        $this->actingAs(User::factory()->create(['status' => 'inactive']))->get(route('student.grades.show', $this->grade))->assertRedirect(route('login'));
        auth()->logout();
        $this->get(route('student.grades.show', $this->grade))->assertRedirect(route('login'));
    }

    public function test_history_is_scoped_paginated_and_stable_when_timestamps_match(): void
    {
        $g = $this->grade;
        foreach (range(1, 12) as $i) {
            $g = app(GradeService::class)->update($g->issued_by, $g->id, $g->version, 'Korekta '.$i, ['value' => $i % 2 === 0 ? 5 : 4]);
        }
        Grade::factory()->create(['topic' => 'Obca ocena']);
        $this->actingAs($g->enrollment->student)->get(route('student.grades.show', $g))
            ->assertInertia(fn (Assert $p) => $p->has('history.data', 10)->where('history.meta.total', 13)
                ->where('history.data.0.reason', 'Korekta 12')->where('history.data.9.reason', 'Korekta 3'));
        $this->get(route('student.grades.show', [$g, 'history_page' => 2]))
            ->assertInertia(fn (Assert $p) => $p->has('history.data', 3)->where('history.data.0.reason', 'Korekta 2')
                ->where('history.data.2.action', 'create')->where('history.meta.currentPage', 2));
    }

    public function test_enrollment_ending_today_is_not_yet_historical(): void
    {
        $g = $this->grade;
        $g->enrollment->update(['ends_on' => now('Europe/Warsaw')->toDateString()]);
        $this->actingAs($g->enrollment->student)->get(route('student.grades.show', $g))
            ->assertInertia(fn (Assert $p) => $p->where('grade.student.historical', false));
    }

    public function test_history_pagination_is_validated_after_authorization(): void
    {
        $url = route('student.grades.show', $this->grade);
        $this->actingAs($this->grade->enrollment->student)->get($url.'?history_page[]=1')->assertStatus(303)->assertRedirect($url)->assertSessionHasErrors('history_page');
        $this->actingAs(User::factory()->create())->get($url.'?history_page[]=1')->assertNotFound();
    }
}
