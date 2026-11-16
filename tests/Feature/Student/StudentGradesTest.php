<?php

namespace Tests\Feature\Student;

use App\Models\Assessment;
use App\Models\Grade;
use App\Models\GradeCategory;
use App\Models\SchoolClass;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** F-102: a student browses only their own grades; filters never change the owner. */
class StudentGradesTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private StudentEnrollment $enrollment;

    private SchoolClass $class;

    private Term $term;

    private Term $term2;

    private Subject $math;

    private GradeCategory $quiz;

    protected function setUp(): void
    {
        parent::setUp();
        $this->student = User::factory()->create(['first_name' => 'Jan', 'last_name' => 'Kowalski']);
        $this->class = SchoolClass::factory()->create();
        $this->enrollment = StudentEnrollment::factory()->create(['student_id' => $this->student->id, 'school_class_id' => $this->class->id]);
        // Fixture clock is 2026-11-15, so term 1 (2026-09-01..) is current.
        $this->term = Term::factory()->create(['academic_year_id' => $this->class->academic_year_id, 'number' => 1, 'starts_on' => '2026-09-01', 'ends_on' => '2027-01-31']);
        $this->term2 = Term::factory()->create(['academic_year_id' => $this->class->academic_year_id, 'number' => 2, 'starts_on' => '2027-02-01', 'ends_on' => '2027-06-30']);
        $this->math = Subject::factory()->create(['name' => 'Matematyka']);
        $this->quiz = GradeCategory::factory()->create(['name' => 'Kartkówka']);
    }

    /** @param array<string, mixed> $grade @param array<string, mixed> $assessment */
    private function grade(int $value, int $weight, array $grade = [], array $assessment = [], ?StudentEnrollment $enrollment = null): Grade
    {
        $header = Assessment::factory()->create([
            'school_class_id' => $enrollment?->school_class_id ?? $this->class->id,
            'subject_id' => $this->math->id, 'term_id' => $this->term->id, 'category_id' => $this->quiz->id,
            'graded_on' => '2026-10-10', 'default_weight' => $weight, ...$assessment,
        ]);

        return Grade::factory()->create([
            'assessment_id' => $header->id, 'student_enrollment_id' => ($enrollment ?? $this->enrollment)->id,
            'value' => $value, 'weight' => $weight, ...$grade,
        ]);
    }

    public function test_student_sees_only_own_grades_with_allowlisted_props(): void
    {
        $this->grade(4, 2, ['topic' => 'Ciągi']);
        $other = User::factory()->create(['email' => 'obcy@example.test']);
        $otherEnrollment = StudentEnrollment::factory()->create(['student_id' => $other->id, 'school_class_id' => $this->class->id]);
        $this->grade(1, 5, ['topic' => 'Cudza'], [], $otherEnrollment);

        $response = $this->actingAs($this->student)->get(route('student.grades.index'))->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('student/grades/index')
            ->has('grades.data', 1)
            ->where('grades.meta.total', 1)
            ->where('grades.data.0.topic', 'Ciągi')
            ->where('grades.data.0.value', 4)
            ->where('grades.data.0.context.subject.label', 'Matematyka')
            ->where('summary.average', '4.00')->where('summary.count', 1)
            ->where('filters.termId', (string) $this->term->id)
            ->has('options.terms', 2)->has('options.subjects', 1)->has('options.categories', 1));
        $body = $response->getContent();
        foreach (['obcy@example.test', 'Cudza', 'password', 'remember_token'] as $secret) {
            $this->assertStringNotContainsString($secret, $body);
        }
    }

    public function test_weighted_average_is_rounded_half_up_and_ignores_pagination(): void
    {
        foreach ([[4, 2], [5, 1], [5, 3], [3, 1]] as $i => [$value, $weight]) {
            $this->grade($value, $weight, ['topic' => 'Ocena '.$i]);
        }
        foreach (range(1, 9) as $i) {
            $this->grade(3, 1, ['topic' => 'Wypełniacz '.$i]);
        }
        // 4*2+5+15+3 = 31 / 7; fillers add 27 / 9 => 58 / 16 = 3.625 => 3.63
        $page1 = $this->actingAs($this->student)->get(route('student.grades.index', ['per_page' => 10]))->assertOk();
        $page1->assertInertia(fn (Assert $p) => $p->has('grades.data', 10)->where('grades.meta.lastPage', 2)
            ->where('summary.average', '3.63')->where('summary.count', 13));
        $this->actingAs($this->student)->get(route('student.grades.index', ['per_page' => 10, 'page' => 2]))
            ->assertInertia(fn (Assert $p) => $p->has('grades.data', 3)->where('summary.average', '3.63')->where('summary.count', 13));
    }

    public function test_deleted_grades_are_excluded_from_list_and_average(): void
    {
        $this->grade(5, 1);
        $this->grade(1, 5)->delete();

        $this->actingAs($this->student)->get(route('student.grades.index'))
            ->assertInertia(fn (Assert $p) => $p->has('grades.data', 1)->where('summary.average', '5.00')->where('summary.count', 1)->where('grades.data.0.deletedAt', null));
    }

    public function test_filters_narrow_list_and_average_but_never_the_owner(): void
    {
        $physics = Subject::factory()->create(['name' => 'Fizyka']);
        $homework = GradeCategory::factory()->create(['name' => 'Praca domowa']);
        $this->grade(5, 1, ['topic' => 'Funkcja liniowa']);
        $this->grade(3, 1, ['topic' => 'Układy równań', 'category_id' => $homework->id], ['graded_on' => '2026-10-20']);
        $this->grade(2, 1, ['topic' => 'Ruch'], ['subject_id' => $physics->id]);
        $get = fn (array $q) => $this->actingAs($this->student)->get(route('student.grades.index', $q));

        $get(['subject_id' => $this->math->id])->assertInertia(fn (Assert $p) => $p->has('grades.data', 2)->where('summary.average', '4.00')
            ->has('subjects', 2)->where('subjects.0.subject.label', 'Fizyka')->where('subjects.0.average', '2.00')
            ->where('subjects.1.average', '4.00')->where('filters.subjectId', (string) $this->math->id));
        $get(['category_id' => $homework->id])->assertInertia(fn (Assert $p) => $p->has('grades.data', 1)->where('summary.average', '3.00'));
        $get(['q' => 'liniowa'])->assertInertia(fn (Assert $p) => $p->has('grades.data', 1)->where('grades.data.0.topic', 'Funkcja liniowa')->where('summary.count', 1));
        $get(['q' => '%'])->assertInertia(fn (Assert $p) => $p->has('grades.data', 0)->where('summary.average', null)->where('summary.count', 0));
        $get(['date_from' => '2026-10-15', 'date_to' => '2026-10-31'])->assertInertia(fn (Assert $p) => $p->has('grades.data', 1)->where('grades.data.0.topic', 'Układy równań'));
    }

    public function test_term_defaults_to_current_and_can_be_switched(): void
    {
        $this->grade(5, 1, ['topic' => 'Semestr 1']);
        $header = Assessment::factory()->create(['school_class_id' => $this->class->id, 'subject_id' => $this->math->id, 'term_id' => $this->term2->id, 'category_id' => $this->quiz->id, 'graded_on' => '2027-03-01']);
        $this->travelTo('2027-03-02 12:00:00 UTC');
        Grade::factory()->create(['assessment_id' => $header->id, 'student_enrollment_id' => $this->enrollment->id, 'value' => 2, 'weight' => 2, 'topic' => 'Semestr 2']);

        $this->actingAs($this->student)->get(route('student.grades.index'))
            ->assertInertia(fn (Assert $p) => $p->where('filters.termId', (string) $this->term2->id)->where('grades.data.0.topic', 'Semestr 2'));
        $this->actingAs($this->student)->get(route('student.grades.index', ['term_id' => $this->term->id]))
            ->assertInertia(fn (Assert $p) => $p->where('grades.data.0.topic', 'Semestr 1')->where('summary.average', '5.00'));
    }

    public function test_ended_enrollment_grades_stay_visible(): void
    {
        $this->grade(4, 1);
        $this->enrollment->update(['ends_on' => '2026-10-31']);

        $this->actingAs($this->student)->get(route('student.grades.index'))
            ->assertInertia(fn (Assert $p) => $p->has('grades.data', 1)->where('summary.count', 1));
    }

    public function test_student_cannot_select_another_student_or_enrollment(): void
    {
        $other = User::factory()->create();
        $otherEnrollment = StudentEnrollment::factory()->create(['student_id' => $other->id, 'school_class_id' => $this->class->id]);
        $this->grade(1, 5, ['topic' => 'Cudza'], [], $otherEnrollment);
        $this->grade(5, 1);

        foreach ([['student_id' => $other->id], ['student_enrollment_id' => $otherEnrollment->id]] as $query) {
            $response = $this->actingAs($this->student)->get(route('student.grades.index', $query))
                ->assertRedirect(route('student.grades.index'))->assertSessionHasErrors(array_key_first($query));
            $this->assertStringNotContainsString('Cudza', $response->getContent());
            $this->followRedirects($response)->assertInertia(fn (Assert $p) => $p->where('summary.count', 1)->where('summary.average', '5.00'));
        }
    }

    public function test_foreign_or_invalid_scopes_are_rejected_without_leaking_counts(): void
    {
        $foreignSubject = Subject::factory()->create(['name' => 'Cudzy przedmiot']);
        $other = User::factory()->create();
        $otherClass = SchoolClass::factory()->create();
        $otherEnrollment = StudentEnrollment::factory()->create(['student_id' => $other->id, 'school_class_id' => $otherClass->id]);
        $foreignTerm = Term::factory()->create(['academic_year_id' => $otherClass->academic_year_id]);
        $header = Assessment::factory()->create(['school_class_id' => $otherClass->id, 'subject_id' => $foreignSubject->id, 'term_id' => $foreignTerm->id]);
        Grade::factory()->create(['assessment_id' => $header->id, 'student_enrollment_id' => $otherEnrollment->id]);
        $this->grade(4, 1);

        foreach ([
            'subject_id' => $foreignSubject->id, 'term_id' => $foreignTerm->id, 'category_id' => 999999,
        ] as $key => $value) {
            $this->actingAs($this->student)->get(route('student.grades.index', [$key => $value]))
                ->assertRedirect(route('student.grades.index'))->assertSessionHasErrors($key);
        }
        $this->actingAs($this->student)->get(route('student.grades.index', ['per_page' => 7, 'sort' => 'topic', 'direction' => 'up', 'date_from' => '2026-12-01', 'date_to' => '2026-11-01']))
            ->assertRedirect(route('student.grades.index', ['date_from' => '2026-12-01']))->assertSessionHasErrors(['per_page', 'sort', 'direction', 'date_to']);
    }

    public function test_page_past_the_end_redirects_to_the_last_page_keeping_filters(): void
    {
        foreach (range(1, 12) as $i) {
            $this->grade(4, 1, ['topic' => 'Ocena '.$i]);
        }

        $this->actingAs($this->student)->get(route('student.grades.index', ['page' => 9, 'q' => 'Ocena']))
            ->assertRedirect(route('student.grades.index', ['page' => 2, 'q' => 'Ocena']))->assertStatus(303);
    }

    public function test_sorting_uses_allowlist_with_stable_tie_break(): void
    {
        $this->grade(2, 1, ['topic' => 'Niska']);
        $this->grade(5, 1, ['topic' => 'Wysoka']);

        $this->actingAs($this->student)->get(route('student.grades.index', ['sort' => 'value', 'direction' => 'desc']))
            ->assertInertia(fn (Assert $p) => $p->where('grades.data.0.topic', 'Wysoka')->where('filters.sort', 'value')->where('filters.direction', 'desc'));
        $this->actingAs($this->student)->get(route('student.grades.index'))
            ->assertInertia(fn (Assert $p) => $p->where('filters.sort', 'graded_on')->where('filters.direction', 'desc')->where('filters.perPage', 10));
    }

    public function test_student_without_enrollment_gets_empty_state_data(): void
    {
        $lonely = User::factory()->create();

        $this->actingAs($lonely)->get(route('student.grades.index'))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->has('grades.data', 0)->where('grades.meta.lastPage', 1)->where('summary.count', 0)
                ->where('summary.average', null)->has('options.terms', 0)->where('filters.termId', null));
    }

    public function test_other_roles_inactive_students_and_guests_are_denied(): void
    {
        $this->actingAs(User::factory()->teacher()->create())->get(route('student.grades.index'))->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get(route('student.grades.index'))->assertForbidden();
        $this->actingAs(User::factory()->create(['status' => 'inactive']))->get(route('student.grades.index'))->assertRedirect(route('login'));
        auth()->logout();
        $this->get(route('student.grades.index'))->assertRedirect(route('login'));
    }

    public function test_navigation_offers_my_grades_to_students_only(): void
    {
        $this->actingAs($this->student)->get(route('student.dashboard'))
            ->assertInertia(fn (Assert $p) => $p->where('navigation.primary.1.href', '/student/grades'));
        $this->actingAs(User::factory()->teacher()->create())->get(route('teacher.dashboard'))
            ->assertInertia(fn (Assert $p) => $p->has('navigation.primary', 3)->where('navigation.primary.1.href', '/teacher/classes')->where('navigation.primary.2.href', '/teacher/history'));
    }
}
