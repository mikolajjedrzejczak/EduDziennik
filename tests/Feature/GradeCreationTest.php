<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Grade;
use App\Models\GradeAudit;
use App\Models\GradeCategory;
use App\Models\SchoolClass;
use App\Models\StudentEnrollment;
use App\Models\TeacherAssignment;
use App\Models\Term;
use App\Models\User;
use App\Services\Grades\GradeService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GradeCreationTest extends TestCase
{
    use RefreshDatabase;

    private TeacherAssignment $assignment;

    private StudentEnrollment $enrollment;

    private Assessment $assessment;

    private array $input;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 16));
        $class = SchoolClass::factory()->create();
        $term = Term::factory()->create(['academic_year_id' => $class->academic_year_id, 'starts_on' => '2026-09-01', 'ends_on' => '2027-01-31']);
        $this->assignment = TeacherAssignment::factory()->create(['school_class_id' => $class->id, 'term_id' => $term->id]);
        $this->enrollment = StudentEnrollment::factory()->create(['school_class_id' => $class->id, 'starts_on' => '2026-09-01', 'ends_on' => '2026-10-31']);
        $this->assessment = Assessment::factory()->create(['school_class_id' => $class->id, 'subject_id' => $this->assignment->subject_id, 'term_id' => $term->id, 'category_id' => GradeCategory::factory()->create()->id, 'topic' => 'Funkcja kwadratowa', 'default_weight' => 2, 'graded_on' => '2026-10-01']);
        $this->input = ['assignment_id' => (string) $this->assignment->id, 'assessment_id' => (string) $this->assessment->id, 'student_enrollment_id' => (string) $this->enrollment->id, 'value' => '4', 'weight' => '5', 'category_id' => (string) $this->assessment->category_id, 'topic' => 'Indywidualny temat', 'graded_on' => '2026-10-02', 'comment' => 'Dobrze'];
    }

    public function test_teacher_and_admin_create_one_grade_and_audit_and_student_can_read(): void
    {
        foreach ([$this->assignment->teacher, User::factory()->admin()->create()] as $actor) {
            $role = $actor->role;
            $this->actingAs($actor)->get(route($role.'.grades.create', $this->input))->assertInertia(fn (Assert $p) => $p->component('shared/grades/create')->where('assessment.defaultWeight', 2)->where('selectedEnrollmentId', (string) $this->enrollment->id)->missing('students.0.email')->has('background'));
            $this->post(route($role.'.grades.store'), [...$this->input, 'issued_by' => 9999, 'version' => 99, 'student_id' => 9999])->assertStatus(303)->assertRedirect(route($role.'.gradebooks.show', $this->assignment));
            $grade = Grade::latest('id')->firstOrFail();
            $this->assertSame($actor->id, $grade->issued_by);
            $this->assertSame(1, $grade->version);
            $this->assertSame(5, $grade->weight);
            $audit = $grade->audits()->sole();
            $this->assertNull($audit->old_values);
            $this->assertSame('create', $audit->action);
            $this->assertSame('Indywidualny temat', $audit->new_values['topic']);
            $this->assertSame((string) $this->enrollment->id, $audit->new_values['student_enrollment_id']);
            $this->assertSame($actor->id, $audit->actor_id);
            $this->assertSame(2, $this->assessment->fresh()->default_weight);
            $this->actingAs($this->enrollment->student)->get(route('student.grades.show', $grade))->assertOk();
            $this->actingAs($actor)->post(route($role.'.grades.store'), $this->input)->assertSessionHasErrors('form');
            $this->assertDatabaseCount('grades', $role === 'teacher' ? 1 : 2);
            $this->assertDatabaseCount('grade_audits', $role === 'teacher' ? 1 : 2);
            $this->enrollment = StudentEnrollment::factory()->create(['school_class_id' => $this->assignment->school_class_id, 'starts_on' => '2026-09-01']);
            $this->input['student_enrollment_id'] = (string) $this->enrollment->id;
        }
    }

    public function test_return_filters_are_validated_and_preserved_without_accepting_a_return_url(): void
    {
        // Enough rows/columns to keep both second pages valid, including per_page=25.
        StudentEnrollment::factory()->count(30)->create(['student_id' => fn () => User::factory()->create(['last_name' => 'Uczeń'])->id, 'school_class_id' => $this->assignment->school_class_id, 'starts_on' => '2026-09-01']);
        Assessment::factory()->count(11)->create(['school_class_id' => $this->assignment->school_class_id, 'subject_id' => $this->assignment->subject_id, 'term_id' => $this->assignment->term_id]);
        $filters = ['q' => 'Uczeń', 'page' => '2', 'per_page' => '25', 'sort' => 'last_name', 'direction' => 'desc', 'assessment_page' => '2'];
        foreach ([$this->assignment->teacher, User::factory()->admin()->create()] as $actor) {
            $url = route($actor->role.'.gradebooks.show', ['assignment' => $this->assignment->id, ...$filters]);
            $payload = [...$this->input, ...$filters, 'return_url' => 'https://example.org', 'assignment' => 999999];
            $this->actingAs($actor)->get(route($actor->role.'.grades.create', $payload))
                ->assertInertia(fn (Assert $p) => $p->where('background.filters.page', 2)->where('background.filters.assessmentPage', 2)
                    ->where('background.filters.q', 'Uczeń')->where('background.filters.perPage', 25)->where('background.filters.direction', 'desc')
                    ->where('cancelUrl', $url)->missing('returnFilters.return_url')->missing('returnFilters.assignment'));
            $this->post(route($actor->role.'.grades.store'), $payload)->assertRedirect($url);
            $this->enrollment = StudentEnrollment::factory()->create(['school_class_id' => $this->assignment->school_class_id, 'starts_on' => '2026-09-01']);
            $this->input['student_enrollment_id'] = (string) $this->enrollment->id;
        }
    }

    public function test_invalid_return_filters_cannot_write_a_grade(): void
    {
        $this->actingAs($this->assignment->teacher);
        foreach (['page' => 0, 'assessment_page' => 100001, 'q' => ['bad'], 'per_page' => 1000, 'direction' => 'invalid', 'sort' => 'email'] as $key => $invalid) {
            $payload = [...$this->input, $key => $invalid];
            $this->get(route('teacher.grades.create', $payload))->assertSessionHasErrors($key);
            $this->post(route('teacher.grades.store'), $payload)->assertSessionHasErrors($key);
        }
        $this->assertDatabaseCount('grades', 0);
        $this->assertDatabaseCount('grade_audits', 0);
    }

    public function test_invalid_fields_have_no_effect_and_preserve_input(): void
    {
        $url = route('teacher.grades.create', $this->input);
        $this->actingAs($this->assignment->teacher)->from($url)->post(route('teacher.grades.store'), [...$this->input, 'weight' => 6, 'value' => 0, 'graded_on' => '2026-12-01', 'topic' => str_repeat('x', 121), 'comment' => str_repeat('x', 501), 'category_id' => 999999])->assertRedirect($url)->assertSessionHasErrors(['weight', 'value', 'graded_on', 'topic', 'comment', 'category_id'])->assertSessionHasInput('topic');
        foreach (['2026-08-31', '2026-11-01', '2027-02-01', 'bad'] as $date) {
            $this->post(route('teacher.grades.store'), [...$this->input, 'graded_on' => $date])->assertSessionHasErrors('graded_on');
        }
        $this->assessment->category->update(['is_active' => false]);
        $this->post(route('teacher.grades.store'), $this->input)->assertSessionHasErrors('category_id');
        $this->assertDatabaseCount('grades', 0);
        $this->assertDatabaseCount('grade_audits', 0);
    }

    public function test_foreign_scope_mismatched_context_and_roles_are_denied(): void
    {
        $this->input = [...$this->input, 'page' => '2', 'assessment_page' => '2', 'q' => 'Uczeń', 'per_page' => '25', 'direction' => 'desc'];
        $this->get(route('teacher.grades.create', $this->input))->assertRedirect(route('login'));
        foreach ([User::factory()->teacher()->create(), $this->enrollment->student] as $actor) {
            $this->actingAs($actor)->get(route('teacher.grades.create', $this->input))->assertStatus($actor->role === 'student' ? 403 : 404);
            $this->post(route('teacher.grades.store'), $this->input)->assertStatus($actor->role === 'student' ? 403 : 404);
        }
        $this->actingAs($this->assignment->teacher);
        foreach ([['assessment_id' => Assessment::factory()->create()->id], ['student_enrollment_id' => StudentEnrollment::factory()->create()->id], ['assignment_id' => TeacherAssignment::factory()->create()->id], ['assignment_id' => ['bad']]] as $override) {
            $this->get(route('teacher.grades.create', [...$this->input, ...$override]))->assertNotFound();
            $this->post(route('teacher.grades.store'), [...$this->input, ...$override])->assertNotFound();
        }
        $this->assignment->update(['revoked_at' => now()]);
        $this->post(route('teacher.grades.store'), $this->input)->assertNotFound();
        $this->assertDatabaseCount('grades', 0);
    }

    public function test_closed_term_inactive_student_and_removed_assessment_are_denied(): void
    {
        foreach ([$this->assignment->teacher, User::factory()->admin()->create()] as $actor) {
            $this->actingAs($actor);
            $this->enrollment->student->update(['status' => 'inactive']);
            $this->post(route($actor->role.'.grades.store'), $this->input)->assertForbidden();
            $this->enrollment->student->update(['status' => 'active']);
        }
        $this->assignment->term->update(['closed_at' => now()]);
        foreach ([$this->assignment->teacher, User::factory()->admin()->create()] as $actor) {
            $this->actingAs($actor)->post(route($actor->role.'.grades.store'), $this->input)->assertSessionHasErrors('form');
        }
        $this->assessment->delete();
        $this->post(route('admin.grades.store'), $this->input)->assertNotFound();
        $this->assertDatabaseCount('grades', 0);
    }

    public function test_form_options_are_bounded_and_selected_cell_remains_available(): void
    {
        $this->actingAs($this->assignment->teacher);
        $this->get(route('teacher.grades.create', ['assignment_id' => $this->assignment->id]))->assertInertia(fn (Assert $p) => $p->has('students', 1)->has('assessments', 1)->where('assessment', null));
        StudentEnrollment::factory()->count(50)->create(['school_class_id' => $this->assignment->school_class_id, 'starts_on' => '2026-09-01']);
        $this->get(route('teacher.grades.create', ['assignment_id' => $this->assignment->id]))->assertRedirect(route('teacher.gradebooks.show', $this->assignment))->assertSessionHasErrors('form');
        $this->get(route('teacher.grades.create', $this->input))->assertInertia(fn (Assert $p) => $p->has('students', 1)->where('selectedEnrollmentId', (string) $this->enrollment->id));
    }

    public function test_changed_enrollment_dates_after_opening_form_prevent_out_of_window_write(): void
    {
        $this->actingAs($this->assignment->teacher)->get(route('teacher.grades.create', $this->input))->assertOk();
        $this->enrollment->update(['ends_on' => '2026-09-30']);
        $this->post(route('teacher.grades.store'), $this->input)->assertSessionHasErrors('graded_on');
        $this->assertDatabaseCount('grades', 0);
        $this->assertDatabaseCount('grade_audits', 0);
    }

    public function test_service_rechecks_revocation_after_opening_form(): void
    {
        $this->actingAs($this->assignment->teacher)->get(route('teacher.grades.create', $this->input))->assertOk();
        $this->assignment->update(['revoked_at' => now()]);
        try {
            app(GradeService::class)->create($this->assignment->teacher_id, $this->enrollment->id, $this->assessment->id, ['value' => 4]);
            $this->fail('Expected revoked assignment denial');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('grades', 0);
            $this->assertDatabaseCount('grade_audits', 0);
        }
    }

    public function test_audit_failure_rolls_back_grade_and_deleted_cells_cannot_be_reused(): void
    {
        $dispatcher = GradeAudit::getEventDispatcher();
        GradeAudit::setEventDispatcher(clone $dispatcher);
        GradeAudit::creating(function (GradeAudit $audit) {
            $audit->actor_id = 99999999;
        });
        try {
            app(GradeService::class)->create($this->assignment->teacher_id, $this->enrollment->id, $this->assessment->id, ['value' => 4]);
            $this->fail('Expected audit insert failure');
        } catch (QueryException $e) {
            $this->assertSame('23000', $e->errorInfo[0]);
        } finally {
            GradeAudit::setEventDispatcher($dispatcher);
        }
        $this->assertDatabaseCount('grades', 0);
        $this->assertDatabaseCount('grade_audits', 0);
        $grade = app(GradeService::class)->create($this->assignment->teacher_id, $this->enrollment->id, $this->assessment->id, ['value' => 4]);
        $grade->delete();
        $this->actingAs($this->assignment->teacher)->post(route('teacher.grades.store'), $this->input)->assertSessionHasErrors('form');
        $this->assertDatabaseCount('grades', 1);
        $this->assertDatabaseCount('grade_audits', 1);
    }
}
