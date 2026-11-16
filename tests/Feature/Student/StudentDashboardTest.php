<?php

namespace Tests\Feature\Student;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Assessment;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StudentDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private StudentEnrollment $enrollment;

    private Term $term;

    private Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->student = User::factory()->create();
        $class = SchoolClass::factory()->create();
        $this->enrollment = StudentEnrollment::factory()->create(['student_id' => $this->student->id, 'school_class_id' => $class->id]);
        $this->term = Term::factory()->create(['academic_year_id' => $class->academic_year_id, 'number' => 1, 'starts_on' => '2026-09-01', 'ends_on' => '2027-01-31']);
        $this->subject = Subject::factory()->create(['name' => 'Matematyka']);
    }

    private function grade(int $value, int $weight, ?Subject $subject = null, ?StudentEnrollment $enrollment = null, ?Term $term = null): Grade
    {
        $enrollment ??= $this->enrollment;
        $assessment = Assessment::factory()->create([
            'school_class_id' => $enrollment->school_class_id, 'subject_id' => ($subject ?? $this->subject)->id,
            'term_id' => ($term ?? $this->term)->id, 'graded_on' => max('2026-10-10', $enrollment->starts_on->toDateString(), ($term ?? $this->term)->starts_on->toDateString()),
        ]);

        return Grade::factory()->create(['assessment_id' => $assessment->id, 'student_enrollment_id' => $enrollment->id, 'value' => $value, 'weight' => $weight]);
    }

    public function test_dashboard_and_list_share_weighted_average_across_subjects(): void
    {
        $physics = Subject::factory()->create(['name' => 'Fizyka']);
        foreach ([[4, 2], [5, 1], [5, 3]] as [$value, $weight]) {
            $this->grade($value, $weight);
        }
        $this->grade(3, 1, $physics);
        foreach (['student.dashboard', 'student.grades.index'] as $route) {
            $this->actingAs($this->student)->get(route($route))->assertOk()->assertInertia(fn (Assert $p) => $p
                ->where('summary.average', '4.43')->where('summary.count', 4));
        }
        $this->actingAs($this->student)->get(route('student.dashboard'))->assertInertia(fn (Assert $p) => $p
            ->has('subjects', 2)->where('subjects.0.subject.label', 'Matematyka')->where('subjects.0.average', '4.67')
            ->where('subjects.1.average', '3.00')->has('recentGrades', 4));
    }

    public function test_scope_includes_transfer_but_excludes_foreign_deleted_and_other_term_grades(): void
    {
        $this->grade(4, 1);
        $this->grade(1, 5)->delete();
        $this->enrollment->update(['ends_on' => '2026-10-31']);
        $newClass = SchoolClass::factory()->create(['academic_year_id' => $this->term->academic_year_id]);
        $newEnrollment = StudentEnrollment::factory()->create(['student_id' => $this->student->id, 'school_class_id' => $newClass->id, 'starts_on' => '2026-11-01']);
        $newGrade = $this->grade(6, 1, enrollment: $newEnrollment);

        $other = StudentEnrollment::factory()->create(['school_class_id' => $newClass->id]);
        $this->grade(1, 5, enrollment: $other);
        $otherTerm = Term::factory()->create(['academic_year_id' => $this->term->academic_year_id, 'number' => 2]);
        $this->travelTo('2027-03-01 12:00:00 UTC');
        $this->grade(1, 5, term: $otherTerm);
        $this->travelTo('2026-11-15 12:00:00 UTC');
        $response = $this->actingAs($this->student)->get(route('student.dashboard'))->assertOk();
        $response->assertInertia(fn (Assert $p) => $p->where('summary.average', '5.00')->where('summary.count', 2)
            ->has('recentGrades', 2)->where('recentGrades.0.id', (string) $newGrade->id)->has('subjects', 1));
        foreach (['password', 'remember_token', $other->student->email] as $secret) {
            $this->assertStringNotContainsString($secret, $response->getContent());
        }
    }

    public function test_recent_grades_limit_and_stable_order_do_not_limit_aggregates(): void
    {
        $ids = [];
        foreach (range(1, 7) as $i) {
            $ids[] = (string) $this->grade(4, 1)->id;
        }
        $this->actingAs($this->student)->get(route('student.dashboard'))->assertInertia(fn (Assert $p) => $p
            ->where('summary.count', 7)->has('recentGrades', 5)->where('recentGrades', fn ($grades) => $grades->pluck('id')->all() === array_slice(array_reverse($ids), 0, 5)));
    }

    public function test_subject_rank_uses_unrounded_averages_and_names_for_exact_ties(): void
    {
        $higher = Subject::factory()->create(['name' => 'Zoologia']);
        $tied = Subject::factory()->create(['name' => 'Fizyka']);
        foreach ([$this->subject, $tied] as $subject) {
            foreach ([[4, 2], [5, 1], [5, 3], [3, 1]] as [$value, $weight]) {
                $this->grade($value, $weight, $subject);
            }
        }
        foreach ([[5, 5], [5, 5], [5, 3], [4, 5], [4, 5], [4, 5], [4, 2]] as [$value, $weight]) {
            $this->grade($value, $weight, $higher);
        }
        // 133/30 > 31/7, although both display 4.43; Fizyka wins the exact tie by name.
        $this->actingAs($this->student)->get(route('student.dashboard'))->assertInertia(fn (Assert $p) => $p
            ->where('subjects.0.subject.label', 'Zoologia')->where('subjects.0.average', '4.43')
            ->where('subjects.1.subject.label', 'Fizyka')->where('subjects.1.average', '4.43')
            ->where('subjects.2.subject.label', 'Matematyka')->where('subjects.2.average', '4.43'));
    }

    public function test_no_grades_and_no_current_term_are_explicit_empty_states_with_manual_selection(): void
    {
        $get = fn (array $query = []) => $this->actingAs($this->student)->get(route('student.dashboard', $query));
        $get()->assertInertia(fn (Assert $p) => $p->where('summary.average', null)->where('summary.count', 0)->has('subjects', 0)->has('recentGrades', 0));
        $this->grade(5, 1);
        $this->travelTo('2027-08-01 12:00:00 UTC');
        $get()->assertInertia(fn (Assert $p) => $p->where('term', null)->where('summary.average', null)->has('terms', 1)->has('subjects', 0)->has('recentGrades', 0));
        $get(['term_id' => $this->term->id])->assertInertia(fn (Assert $p) => $p->where('summary.average', '5.00'));
        $this->actingAs(User::factory()->create())->get(route('student.dashboard'))->assertInertia(fn (Assert $p) => $p->where('term', null)->has('terms', 0));
    }

    public function test_invalid_filters_cannot_choose_an_owner_or_foreign_term_even_with_partial_reload(): void
    {
        $foreignTerm = Term::factory()->create();
        foreach (['term_id' => $foreignTerm->id, 'student_id' => 999, 'student_enrollment_id' => 999] as $key => $value) {
            $this->actingAs($this->student)->get(route('student.dashboard', [$key => $value]), ['X-Inertia' => 'true', 'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()), 'X-Inertia-Partial-Component' => 'student/dashboard', 'X-Inertia-Partial-Data' => 'summary'])
                ->assertStatus(303)->assertRedirect(route('student.dashboard'))->assertSessionHasErrors($key);
        }
    }

    public function test_dashboard_denies_other_roles_inactive_accounts_and_guests(): void
    {
        foreach (['teacher', 'admin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get(route('student.dashboard'))->assertForbidden();
        }
        $this->actingAs(User::factory()->create(['status' => 'inactive']))->get(route('student.dashboard'))->assertRedirect(route('login'));
        auth()->logout();
        $this->get(route('student.dashboard'))->assertRedirect(route('login'));
    }
}
