<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Grade;
use App\Models\GradeCategory;
use App\Models\SchoolClass;
use App\Models\StudentEnrollment;
use App\Models\TeacherAssignment;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GradebookTest extends TestCase
{
    use RefreshDatabase;

    private TeacherAssignment $assignment;

    private StudentEnrollment $enrollment;

    private array $input;

    protected function setUp(): void
    {
        parent::setUp();
        $class = SchoolClass::factory()->create();
        $term = Term::factory()->create(['academic_year_id' => $class->academic_year_id, 'starts_on' => '2026-09-01', 'ends_on' => '2027-01-31']);
        $this->assignment = TeacherAssignment::factory()->create(['school_class_id' => $class->id, 'term_id' => $term->id]);
        $this->enrollment = StudentEnrollment::factory()->create(['school_class_id' => $class->id, 'starts_on' => '2026-09-01', 'ends_on' => '2026-10-31']);
        $this->input = ['category_id' => GradeCategory::factory()->create()->id, 'topic' => 'Ciągi', 'default_weight' => '2', 'graded_on' => '2026-10-01'];
    }

    private function header(): Assessment
    {
        return Assessment::factory()->create(['school_class_id' => $this->assignment->school_class_id, 'subject_id' => $this->assignment->subject_id, 'term_id' => $this->assignment->term_id]);
    }

    public function test_teacher_and_admin_create_empty_columns_without_changing_grades_or_averages(): void
    {
        Grade::factory()->create(['assessment_id' => $this->header()->id, 'student_enrollment_id' => $this->enrollment->id, 'value' => 4, 'weight' => 2]);
        foreach (['teacher' => $this->assignment->teacher, 'admin' => User::factory()->admin()->create()] as $role => $actor) {
            $this->actingAs($actor)->post(route($role.'.assessments.store', $this->assignment), [...$this->input, 'school_class_id' => 999999, 'term_id' => 999999, 'subject_id' => 999999, 'created_by' => 999999])
                ->assertStatus(303)->assertRedirect(route($role.'.gradebooks.show', $this->assignment));
            $header = Assessment::latest('id')->first();
            $this->assertSame($this->assignment->school_class_id, $header->school_class_id);
            $this->assertSame($actor->id, $header->created_by);
            $this->assertSame(0, $header->grades()->count());
            $this->get(route($role.'.gradebooks.show', $this->assignment))->assertInertia(fn (Assert $p) => $p->where('students.data.0.summary.average', '4.00')->has('students.data.0.grades', 1));
        }
        $this->assertDatabaseCount('grades', 1);
        $this->assertDatabaseCount('grade_audits', 1);
    }

    public function test_empty_column_requires_confirmation_and_is_soft_deleted_but_history_blocks_deletion(): void
    {
        $empty = $this->header();
        $this->actingAs($this->assignment->teacher)->delete(route('teacher.assessments.destroy', $empty))->assertSessionHasErrors('confirmed');
        $this->delete(route('teacher.assessments.destroy', $empty), ['confirmed' => true])->assertStatus(303);
        $this->assertSoftDeleted($empty);
        $this->delete(route('teacher.assessments.destroy', $empty), ['confirmed' => true])->assertNotFound();
        foreach ([false, true] as $deleted) {
            $header = $this->header();
            $grade = Grade::factory()->create(['assessment_id' => $header->id, 'student_enrollment_id' => $this->enrollment->id]);
            if ($deleted) {
                $grade->delete();
            }
            $this->from(route('teacher.gradebooks.show', $this->assignment))->delete(route('teacher.assessments.destroy', $header), ['confirmed' => true])->assertSessionHasErrors('assessment');
            $this->assertNotSoftDeleted($header);
        }
    }

    public function test_direct_requests_cannot_escape_scope_or_bypass_read_only_and_date_rules(): void
    {
        $actor = $this->assignment->teacher;
        $foreign = TeacherAssignment::factory()->create();
        $this->actingAs($actor)->post(route('teacher.assessments.store', $foreign), $this->input)->assertNotFound();
        $header = $this->header();
        $this->actingAs(User::factory()->teacher()->create())->delete(route('teacher.assessments.destroy', $header), ['confirmed' => true])->assertNotFound();
        $this->actingAs($actor)->post(route('teacher.assessments.store', $this->assignment), [...$this->input, 'default_weight' => 0, 'graded_on' => '2028-01-01', 'topic' => str_repeat('a', 121), 'category_id' => 99999])->assertSessionHasErrors(['default_weight', 'graded_on', 'topic', 'category_id']);
        GradeCategory::find($this->input['category_id'])->update(['is_active' => false]);
        $this->post(route('teacher.assessments.store', $this->assignment), $this->input)->assertSessionHasErrors('category_id');
        $this->assignment->term->update(['closed_at' => now()]);
        foreach ([$actor, User::factory()->admin()->create()] as $writer) {
            $role = $writer->role;
            $this->actingAs($writer)->post(route($role.'.assessments.store', $this->assignment), $this->input)->assertSessionHasErrors('form');
            $this->delete(route($role.'.assessments.destroy', $header), ['confirmed' => true])->assertSessionHasErrors('term');
        }
        $this->assertDatabaseCount('assessments', 1);
        $this->assertNotSoftDeleted($header);
    }

    public function test_student_details_respect_both_nesting_and_subject_and_do_not_expose_email(): void
    {
        $grade = Grade::factory()->create(['assessment_id' => $this->header()->id, 'student_enrollment_id' => $this->enrollment->id, 'value' => 5]);
        $other = TeacherAssignment::factory()->create(['teacher_id' => $this->assignment->teacher_id, 'school_class_id' => $this->assignment->school_class_id, 'term_id' => $this->assignment->term_id]);
        Grade::factory()->create(['assessment_id' => Assessment::factory()->create(['school_class_id' => $other->school_class_id, 'subject_id' => $other->subject_id, 'term_id' => $other->term_id])->id, 'student_enrollment_id' => $this->enrollment->id, 'value' => 1]);
        foreach (['teacher' => $this->assignment->teacher, 'admin' => User::factory()->admin()->create()] as $role => $actor) {
            $url = route($role.'.students.show', [$this->assignment, $this->enrollment]);
            $response = $this->actingAs($actor)->get($url)->assertOk()->assertInertia(fn (Assert $p) => $p->component('shared/students/show')->has('grades.data', 1)->where('grades.data.0.id', (string) $grade->id)->where('summary.average', '5.00')->where('student.historical', true)->has('recentHistory', 1)->where('recentHistory.0.value', 5));
            $this->assertStringNotContainsString($this->enrollment->student->email, $response->getContent());
            $this->get(route($role.'.students.show', [$this->assignment, StudentEnrollment::factory()->create()]))->assertNotFound();
            $this->get($url.'?page=99')->assertStatus(303)->assertRedirect($url.'?page=1');
            $this->get($url.'?page=-1')->assertStatus(303)->assertRedirect($url)->assertSessionHasErrors('page');
        }
    }

    public function test_lists_scope_grades_options_filters_and_aggregates_before_pagination(): void
    {
        foreach (range(1, 12) as $i) {
            Grade::factory()->create(['assessment_id' => $this->header()->id, 'student_enrollment_id' => $this->enrollment->id, 'value' => 4, 'weight' => 2]);
        }
        $foreign = Grade::factory()->create();
        $this->actingAs($this->assignment->teacher)->get(route('teacher.grades.index'))->assertOk()->assertInertia(fn (Assert $p) => $p->component('teacher/grades/index')->has('grades.data', 10)->where('grades.meta.total', 12)->where('summary.count', 12)->where('summary.average', '4.00')->has('options.subjects', 1)->has('options.enrollments', 1));
        $this->get(route('teacher.grades.index', ['student_enrollment_id' => $foreign->student_enrollment_id]))->assertStatus(303)->assertSessionHasErrors('student_enrollment_id');
        $this->get(route('teacher.grades.index', ['student_id' => $foreign->enrollment->student_id]))->assertStatus(303)->assertSessionHasErrors('student_id');
        $this->get(route('teacher.grades.index', ['q' => '%']))->assertInertia(fn (Assert $p) => $p->has('grades.data', 0)->where('summary.average', null));
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.grades.index', ['term_id' => $this->assignment->term_id, 'school_class_id' => $this->assignment->school_class_id]))->assertOk()->assertInertia(fn (Assert $p) => $p->where('grades.meta.total', 12)->has('options.classes'));
        $this->get(route('admin.gradebooks.index'))->assertOk()->assertInertia(fn (Assert $p) => $p->component('admin/gradebooks/index')->has('assignments.data'));
    }

    public function test_empty_student_detail_and_history_remain_readable_and_archived_assessments_are_hidden(): void
    {
        $header = $this->header();
        $header->delete();
        $actor = $this->assignment->teacher;
        $this->actingAs($actor)->get(route('teacher.gradebooks.show', $this->assignment))->assertInertia(fn (Assert $p) => $p->has('assessments.data', 0)->where('students.data.0.summary.average', null));
        $response = $this->get(route('teacher.students.show', [$this->assignment, $this->enrollment]))->assertInertia(fn (Assert $p) => $p->has('grades.data', 0)->has('recentHistory', 0)->where('summary.average', null));
        $this->get($response->inertiaProps('historyUrl'))->assertOk()->assertInertia(fn (Assert $p) => $p->has('audits.data', 0)->where('filters.studentEnrollmentId', (string) $this->enrollment->id));
        $this->delete(route('teacher.assessments.destroy', $header), ['confirmed' => true])->assertNotFound();
    }

    public function test_guest_wrong_role_inactive_and_revoked_access_are_denied(): void
    {
        $routes = [route('teacher.students.show', [$this->assignment, $this->enrollment]), route('teacher.grades.index')];
        foreach ($routes as $url) {
            auth()->logout();
            $this->get($url)->assertRedirect(route('login'));
            $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
            $this->actingAs(User::factory()->teacher()->create(['status' => 'inactive']))->get($url)->assertRedirect(route('login'));
        }
        $this->assignment->update(['revoked_at' => now()]);
        $this->actingAs($this->assignment->teacher)->get($routes[0])->assertNotFound();
        $this->post(route('teacher.assessments.store', $this->assignment), $this->input)->assertNotFound();
        $this->actingAs(User::factory()->create())->post(route('teacher.assessments.store', $this->assignment), $this->input)->assertForbidden();
    }
}
