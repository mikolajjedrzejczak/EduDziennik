<?php

namespace Tests\Feature\Teacher;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Assessment;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TeacherClassesTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private SchoolClass $class;

    private Term $term;

    protected function setUp(): void
    {
        parent::setUp();
        $this->teacher = User::factory()->teacher()->create();
        $this->class = SchoolClass::factory()->create(['name' => '2A']);
        $this->term = Term::factory()->create(['academic_year_id' => $this->class->academic_year_id, 'starts_on' => '2026-09-01', 'ends_on' => '2027-01-31']);
    }

    /** @param array<string, mixed> $data */
    private function assignment(array $data = []): TeacherAssignment
    {
        return TeacherAssignment::factory()->create(['teacher_id' => $this->teacher->id, 'school_class_id' => $this->class->id, 'term_id' => $this->term->id, ...$data]);
    }

    public function test_list_keeps_subjects_separate_and_excludes_foreign_and_revoked_assignments(): void
    {
        $a = $this->assignment(['subject_id' => Subject::factory()->create(['name' => 'Matematyka'])->id]);
        $b = $this->assignment(['subject_id' => Subject::factory()->create(['name' => 'Fizyka'])->id]);
        $this->assignment(['teacher_id' => User::factory()->teacher()->create(['email' => 'foreign@example.test'])->id]);
        $this->assignment(['revoked_at' => now()]);
        $response = $this->actingAs($this->teacher)->get(route('teacher.gradebooks.index'))->assertOk();
        $response->assertInertia(fn (Assert $p) => $p->component('teacher/gradebooks/index')->has('assignments.data', 2)
            ->where('assignments.data.0.id', (string) $a->id)->where('assignments.data.1.id', (string) $b->id)
            ->where('filters.termId', (string) $this->term->id)->has('terms', 1)
            ->where('navigation.primary.1.href', '/teacher/classes'));
        foreach (['foreign@example.test', 'password', 'remember_token', 'teacher_id'] as $secret) {
            $this->assertStringNotContainsString($secret, $response->getContent());
        }
    }

    public function test_closed_archived_context_is_readable_but_revocation_immediately_removes_access(): void
    {
        $a = $this->assignment();
        $this->term->update(['closed_at' => now()]);
        $this->class->delete();
        $a->subject->delete();
        $this->actingAs($this->teacher)->get(route('teacher.gradebooks.index'))
            ->assertInertia(fn (Assert $p) => $p->has('assignments.data', 1));
        $this->get(route('teacher.gradebooks.show', $a))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('context.assignmentId', (string) $a->id)->where('context.closed', true));
        $a->update(['revoked_at' => now()]);
        $this->get(route('teacher.gradebooks.index'))->assertInertia(fn (Assert $p) => $p->has('assignments.data', 0)->has('terms', 0));
        $this->get(route('teacher.gradebooks.show', $a))->assertNotFound();
        $this->get(route('teacher.gradebooks.show', $a), ['X-Inertia' => 'true', 'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()) ?? '', 'X-Inertia-Partial-Component' => 'shared/gradebooks/show', 'X-Inertia-Partial-Data' => 'students'])->assertNotFound();
    }

    public function test_foreign_or_missing_context_returns_404_even_for_another_subject_of_the_same_class(): void
    {
        $this->assignment();
        $foreign = $this->assignment(['teacher_id' => User::factory()->teacher()->create()->id]);
        $this->actingAs($this->teacher)->get(route('teacher.gradebooks.show', $foreign))->assertNotFound();
        $this->get(route('teacher.gradebooks.show', 999999))->assertNotFound();
    }

    public function test_term_options_are_scoped_and_no_current_term_requires_explicit_selection(): void
    {
        $a = $this->assignment();
        $this->travelTo('2027-08-01 12:00:00 Europe/Warsaw');
        $this->actingAs($this->teacher)->get(route('teacher.gradebooks.index'))->assertInertia(fn (Assert $p) => $p->where('filters.termId', null)->has('terms', 1)->has('assignments.data', 0));
        $this->get(route('teacher.gradebooks.index', ['term_id' => $this->term->id]))->assertInertia(fn (Assert $p) => $p->where('assignments.data.0.id', (string) $a->id));
        $foreign = Term::factory()->create();
        $this->get(route('teacher.gradebooks.index', ['term_id' => $foreign->id, 'q' => '2A']))->assertStatus(303)->assertRedirect(route('teacher.gradebooks.index', ['q' => '2A']))->assertSessionHasErrors('term_id');
        $this->travelTo('2026-08-31 22:30:00 UTC');
        $this->get(route('teacher.gradebooks.index'))->assertInertia(fn (Assert $p) => $p->where('filters.termId', (string) $this->term->id));
    }

    public function test_filters_pagination_and_stable_sorting(): void
    {
        foreach (range(1, 12) as $i) {
            $this->assignment();
        }
        $base = ['term_id' => $this->term->id, 'q' => '2A', 'per_page' => 10];
        $this->actingAs($this->teacher)->get(route('teacher.gradebooks.index', $base))->assertInertia(fn (Assert $p) => $p->has('assignments.data', 10)->where('assignments.meta.total', 12)->where('assignments.meta.lastPage', 2));
        $this->get(route('teacher.gradebooks.index', [...$base, 'page' => 2]))->assertInertia(fn (Assert $p) => $p->has('assignments.data', 2));
        $this->get(route('teacher.gradebooks.index', [...$base, 'page' => 99]))->assertStatus(303)->assertRedirect(route('teacher.gradebooks.index', ['term_id' => $this->term->id, 'q' => '2A', 'page' => 2, 'per_page' => 10]));
        $this->get(route('teacher.gradebooks.index', ['q' => '%']))->assertInertia(fn (Assert $p) => $p->has('assignments.data', 0));
        $this->get(route('teacher.gradebooks.index', ['sort' => 'teacher_id', 'direction' => 'up', 'per_page' => 1, 'page' => -1, 'q' => str_repeat('a', 101)]))->assertStatus(303)->assertRedirect(route('teacher.gradebooks.index'))->assertSessionHasErrors(['sort', 'direction', 'per_page', 'page', 'q']);
    }

    public function test_selection_returns_only_the_exact_subject_and_overlapping_roster(): void
    {
        $a = $this->assignment();
        $b = $this->assignment();
        $student = User::factory()->create(['email' => 'pupil@example.test']);
        $e = StudentEnrollment::factory()->create(['student_id' => $student->id, 'school_class_id' => $this->class->id, 'starts_on' => '2026-09-01', 'ends_on' => '2026-10-31']);
        StudentEnrollment::factory()->create(['school_class_id' => $this->class->id, 'starts_on' => '2027-02-01']);
        $header = Assessment::factory()->create(['school_class_id' => $this->class->id, 'subject_id' => $a->subject_id, 'term_id' => $this->term->id]);
        $other = Assessment::factory()->create(['school_class_id' => $this->class->id, 'subject_id' => $b->subject_id, 'term_id' => $this->term->id]);
        Grade::factory()->create(['student_enrollment_id' => $e->id, 'assessment_id' => $header->id, 'value' => 5, 'weight' => 1]);
        Grade::factory()->create(['student_enrollment_id' => $e->id, 'assessment_id' => $other->id, 'value' => 1, 'weight' => 1]);
        $response = $this->actingAs($this->teacher)->get(route('teacher.gradebooks.show', $a))->assertOk();
        $response->assertInertia(fn (Assert $p) => $p->component('shared/gradebooks/show')->has('assessments.data', 1)->has('students.data', 1)
            ->where('context.assignmentId', (string) $a->id)->where('students.data.0.student.historical', true)
            ->has('students.data.0.grades', 1)->where('students.data.0.summary.average', '5.00'));
        $this->assertStringNotContainsString('pupil@example.test', $response->getContent());
    }

    public function test_gradebook_paginates_columns_without_narrowing_averages_and_marks_deleted_cells(): void
    {
        $a = $this->assignment();
        $e = StudentEnrollment::factory()->create(['school_class_id' => $this->class->id, 'starts_on' => '2026-09-01']);
        foreach (range(1, 12) as $i) {
            $header = Assessment::factory()->create(['school_class_id' => $this->class->id, 'subject_id' => $a->subject_id, 'term_id' => $this->term->id, 'graded_on' => '2026-10-10']);
            $grade = Grade::factory()->create(['assessment_id' => $header->id, 'student_enrollment_id' => $e->id, 'value' => $i === 12 ? 1 : 5, 'weight' => 1]);
            if ($i === 1) {
                $grade->delete();
            }
        }
        $this->actingAs($this->teacher)->get(route('teacher.gradebooks.show', $a))->assertInertia(fn (Assert $p) => $p
            ->has('assessments.data', 10)->has('students.data.0.grades', 9)->has('students.data.0.deletedAssessmentIds', 1)
            ->where('students.data.0.summary.count', 11)->where('students.data.0.summary.average', '4.64'));
        $this->get(route('teacher.gradebooks.show', ['assignment' => $a->id, 'assessment_page' => 2, 'subject_id' => 999999, 'term_id' => 999999]))->assertInertia(fn (Assert $p) => $p
            ->has('assessments.data', 2)->has('students.data.0.grades', 2)->where('students.data.0.summary.average', '4.64'));
        $this->get(route('teacher.gradebooks.show', ['assignment' => $a->id, 'assessment_page' => 99, 'page' => 99]))
            ->assertStatus(303)->assertRedirect(route('teacher.gradebooks.show', ['assignment' => $a->id, 'page' => 1, 'assessment_page' => 2]));
        $this->get(route('teacher.gradebooks.show', ['assignment' => $a->id, 'q' => 'missing']))->assertInertia(fn (Assert $p) => $p->has('students.data', 0));
    }

    public function test_guests_other_roles_and_inactive_accounts_are_denied(): void
    {
        $a = $this->assignment();
        foreach (['teacher.gradebooks.index' => [], 'teacher.gradebooks.show' => ['assignment' => $a->id]] as $route => $params) {
            auth()->logout();
            $this->get(route($route, $params))->assertRedirect(route('login'));
            foreach ([User::factory()->create(), User::factory()->admin()->create()] as $user) {
                $this->actingAs($user)->get(route($route, $params))->assertForbidden();
            }
            $this->actingAs(User::factory()->teacher()->create(['status' => 'inactive']))->get(route($route, $params))->assertRedirect(route('login'));
        }
    }
}
