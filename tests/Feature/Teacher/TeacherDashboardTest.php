<?php

namespace Tests\Feature\Teacher;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Assessment;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\StudentEnrollment;
use App\Models\TeacherAssignment;
use App\Models\Term;
use App\Models\User;
use App\Services\Statistics\TeacherDashboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TeacherDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private TeacherAssignment $assignment;

    private StudentEnrollment $enrollment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->teacher = User::factory()->teacher()->create();
        $this->assignment = TeacherAssignment::factory()->create(['teacher_id' => $this->teacher->id]);
        $this->enrollment = StudentEnrollment::factory()->create(['school_class_id' => $this->assignment->school_class_id]);
    }

    private function assessment(?TeacherAssignment $assignment = null, string $date = '2026-10-10'): Assessment
    {
        $a = $assignment ?? $this->assignment;

        return Assessment::factory()->create(['school_class_id' => $a->school_class_id, 'subject_id' => $a->subject_id,
            'term_id' => $a->term_id, 'graded_on' => $date]);
    }

    private function grade(Assessment $assessment, int $value = 1, int $weight = 1, ?StudentEnrollment $enrollment = null): Grade
    {
        return Grade::factory()->create(['assessment_id' => $assessment->id, 'student_enrollment_id' => ($enrollment ?? $this->enrollment)->id,
            'value' => $value, 'weight' => $weight]);
    }

    public function test_counts_and_recent_grades_include_only_exact_non_revoked_contexts_not_authorship(): void
    {
        $own = $this->grade($this->assessment());
        $this->assessment();
        $this->grade($this->assessment())->delete();
        $foreign = TeacherAssignment::factory()->create(['school_class_id' => $this->assignment->school_class_id, 'term_id' => $this->assignment->term_id]);
        $foreignGrade = $this->grade($this->assessment($foreign));
        // Being author does not grant access to the foreign subject.
        $foreignGrade->update(['issued_by' => $this->teacher->id]);
        $revoked = TeacherAssignment::factory()->create(['teacher_id' => $this->teacher->id, 'school_class_id' => $this->assignment->school_class_id,
            'term_id' => $this->assignment->term_id, 'revoked_at' => now()]);
        $this->grade($this->assessment($revoked));
        TeacherAssignment::factory()->create(['school_class_id' => $this->assignment->school_class_id, 'subject_id' => $this->assignment->subject_id,
            'term_id' => $this->assignment->term_id]);
        $response = $this->actingAs($this->teacher)->get(route('teacher.dashboard'))->assertOk();
        $response->assertInertia(fn (Assert $p) => $p->component('teacher/dashboard')->where('assignmentCount', 1)->where('ungradedCount', 2)
            ->where('atRiskCount', 1)->has('assignments', 1)->has('recentGrades', 1)->where('recentGrades.0.id', (string) $own->id));
        $this->assertStringNotContainsString($this->enrollment->student->email, $response->getContent());
        $this->assertStringNotContainsString('password', $response->getContent());
        $this->assignment->update(['revoked_at' => now()]);
        $this->get(route('teacher.dashboard'))->assertInertia(fn (Assert $p) => $p->where('term', null)->where('assignmentCount', 0)->has('recentGrades', 0));
    }

    public function test_ungraded_cells_respect_inclusive_enrollment_dates_and_deleted_columns(): void
    {
        $this->enrollment->update(['starts_on' => '2026-10-10', 'ends_on' => '2026-10-20']);
        foreach (['2026-10-09', '2026-10-10', '2026-10-20', '2026-10-21'] as $date) {
            $this->assessment(date: $date);
        }
        $this->assessment()->delete();
        $this->actingAs($this->teacher)->get(route('teacher.dashboard'))->assertInertia(fn (Assert $p) => $p->where('ungradedCount', 2)->where('atRiskCount', 0));
    }

    public function test_at_risk_is_a_student_subject_pair_using_unrounded_weighted_average(): void
    {
        $second = TeacherAssignment::factory()->create(['teacher_id' => $this->teacher->id, 'school_class_id' => $this->assignment->school_class_id,
            'term_id' => $this->assignment->term_id]);
        $this->grade($this->assessment());
        $this->grade($this->assessment($second));
        $this->actingAs($this->teacher)->get(route('teacher.dashboard'))->assertInertia(fn (Assert $p) => $p->where('atRiskCount', 2));
        // 499/250 = 1.996 displays 2.00 but is still below the risk threshold.
        foreach (range(1, 49) as $i) {
            $this->grade($this->assessment(), 2, 5);
        }
        $this->grade($this->assessment(), 2, 4);
        $this->actingAs($this->teacher)->get(route('teacher.dashboard'))->assertInertia(fn (Assert $p) => $p->where('atRiskCount', 2));
        $this->grade($this->assessment(), 3);
        $this->actingAs($this->teacher)->get(route('teacher.dashboard'))->assertInertia(fn (Assert $p) => $p->where('atRiskCount', 1));
    }

    public function test_recent_limit_does_not_limit_counts_and_query_count_does_not_grow_with_rows(): void
    {
        $service = app(TeacherDashboard::class);
        $this->grade($this->assessment());
        $measure = function () use ($service): array {
            DB::enableQueryLog();
            DB::flushQueryLog();
            $props = $service->props($this->teacher, $this->assignment->term_id);
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();

            return [$props, $count];
        };
        [, $small] = $measure();
        $ids = [];
        foreach (range(1, 7) as $i) {
            $ids[] = (string) $this->grade($this->assessment())->id;
        }
        [$props, $large] = $measure();
        $this->assertSame($small, $large);
        $this->assertLessThanOrEqual(25, $large);
        $this->assertSame(array_slice(array_reverse($ids), 0, 5), array_column($props['recentGrades'], 'id'));
        $this->assertSame(1, $props['atRiskCount']);
        $this->assertSame(0, $props['ungradedCount']);
    }

    public function test_no_assignments_and_no_current_term_are_empty_with_explicit_term_selection(): void
    {
        $empty = User::factory()->teacher()->create();
        $this->actingAs($empty)->get(route('teacher.dashboard'))->assertInertia(fn (Assert $p) => $p->where('term', null)->has('terms', 0)
            ->where('assignmentCount', 0)->where('ungradedCount', 0)->where('atRiskCount', 0)->has('recentGrades', 0)->has('assignments', 0));
        $this->travelTo('2027-08-01 12:00:00 UTC');
        $this->actingAs($this->teacher)->get(route('teacher.dashboard'))->assertInertia(fn (Assert $p) => $p->where('term', null)->has('terms', 1)->where('assignmentCount', 0));
        $this->get(route('teacher.dashboard', ['term_id' => $this->assignment->term_id]))->assertInertia(fn (Assert $p) => $p->where('assignmentCount', 1));
    }

    public function test_invalid_filters_redirect_safely_also_on_partial_reload(): void
    {
        $foreign = Term::factory()->create();
        foreach (['term_id' => $foreign->id, 'student_id' => 1, 'student_enrollment_id' => 1] as $key => $value) {
            $this->actingAs($this->teacher)->get(route('teacher.dashboard', [$key => $value]), [
                'X-Inertia' => 'true', 'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
                'X-Inertia-Partial-Component' => 'teacher/dashboard', 'X-Inertia-Partial-Data' => 'recentGrades',
            ])->assertStatus(303)->assertRedirect(route('teacher.dashboard'))->assertSessionHasErrors($key);
        }
    }

    public function test_transfer_groups_risk_by_student_and_subject_across_visible_classes(): void
    {
        $this->enrollment->update(['ends_on' => '2026-10-31']);
        $this->grade($this->assessment());
        $class = SchoolClass::factory()->create(['academic_year_id' => $this->assignment->term->academic_year_id]);
        $assignment = TeacherAssignment::factory()->create(['teacher_id' => $this->teacher->id, 'school_class_id' => $class->id,
            'subject_id' => $this->assignment->subject_id, 'term_id' => $this->assignment->term_id]);
        $enrollment = StudentEnrollment::factory()->create(['student_id' => $this->enrollment->student_id,
            'school_class_id' => $class->id, 'starts_on' => '2026-11-01']);
        $this->grade($this->assessment($assignment, '2026-11-10'), 3, enrollment: $enrollment);
        $this->actingAs($this->teacher)->get(route('teacher.dashboard'))->assertInertia(fn (Assert $p) => $p
            ->where('assignmentCount', 2)->where('atRiskCount', 0));
        $this->grade($this->assessment($assignment, '2026-11-10'), 1, enrollment: $enrollment);
        $this->get(route('teacher.dashboard'))->assertInertia(fn (Assert $p) => $p->where('atRiskCount', 1));
    }

    public function test_preview_limit_does_not_hide_counts_and_other_classes_or_terms_are_excluded(): void
    {
        foreach (range(1, 6) as $i) {
            $a = TeacherAssignment::factory()->create(['teacher_id' => $this->teacher->id, 'school_class_id' => $this->assignment->school_class_id,
                'term_id' => $this->assignment->term_id]);
            $this->assessment($a);
        }
        $foreign = TeacherAssignment::factory()->create(['subject_id' => $this->assignment->subject_id, 'term_id' => $this->assignment->term_id]);
        $this->assessment($foreign);
        $otherTerm = Term::factory()->create(['academic_year_id' => $this->assignment->term->academic_year_id, 'number' => 2, 'starts_on' => '2027-02-01', 'ends_on' => '2027-06-30']);
        $a = TeacherAssignment::factory()->create(['teacher_id' => $this->teacher->id, 'school_class_id' => $this->assignment->school_class_id,
            'subject_id' => $this->assignment->subject_id, 'term_id' => $otherTerm->id]);
        $this->assessment($a, '2027-03-10');
        $this->actingAs($this->teacher)->get(route('teacher.dashboard'))->assertInertia(fn (Assert $p) => $p
            ->where('assignmentCount', 7)->where('ungradedCount', 6)->has('assignments', 5));
    }

    public function test_other_roles_inactive_users_and_guests_cannot_access_dashboard(): void
    {
        foreach (['student', 'admin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get(route('teacher.dashboard'))->assertForbidden();
        }
        $this->actingAs(User::factory()->teacher()->create(['status' => 'inactive']))->get(route('teacher.dashboard'))->assertRedirect(route('login'));
        auth()->logout();
        $this->get(route('teacher.dashboard'))->assertRedirect(route('login'));
    }
}
