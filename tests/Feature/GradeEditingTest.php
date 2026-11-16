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
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GradeEditingTest extends TestCase
{
    use RefreshDatabase;

    private Grade $grade;

    private TeacherAssignment $assignment;

    private array $input;

    protected function setUp(): void
    {
        parent::setUp();
        $class = SchoolClass::factory()->create();
        $term = Term::factory()->create(['academic_year_id' => $class->academic_year_id, 'starts_on' => '2026-09-01', 'ends_on' => '2027-01-31']);
        $this->assignment = TeacherAssignment::factory()->create(['school_class_id' => $class->id, 'term_id' => $term->id]);
        $enrollment = StudentEnrollment::factory()->create(['school_class_id' => $class->id, 'starts_on' => '2026-09-01', 'ends_on' => '2026-10-31']);
        $assessment = Assessment::factory()->create(['school_class_id' => $class->id, 'subject_id' => $this->assignment->subject_id, 'term_id' => $term->id, 'category_id' => GradeCategory::factory()->create()->id, 'graded_on' => '2026-10-01']);
        $this->grade = app(GradeService::class)->create($this->assignment->teacher_id, $enrollment->id, $assessment->id, ['value' => 3]);
        $this->input = ['assignment_id' => (string) $this->assignment->id, 'version' => 1, 'reason' => 'Korekta punktacji', 'value' => '4', 'weight' => '5', 'category_id' => (string) GradeCategory::factory()->create()->id, 'topic' => 'Poprawiony temat', 'comment' => 'Nowy komentarz', 'graded_on' => '2026-10-02'];
    }

    public function test_teacher_and_admin_edit_preserves_identity_and_records_snapshots(): void
    {
        foreach ([$this->assignment->teacher, User::factory()->admin()->create()] as $actor) {
            $this->actingAs($actor)->get(route($actor->role.'.grades.edit', $this->grade))->assertInertia(fn (Assert $p) => $p->component('shared/grades/edit')->where('grade.version', $this->grade->version)->has('students', 1)->has('assessments', 1)->missing('students.0.email'));
            $before = $this->grade->only(['student_enrollment_id', 'assessment_id', 'issued_by']);
            $this->patch(route($actor->role.'.grades.update', $this->grade), [...$this->input, 'version' => $this->grade->version, 'value' => $actor->role === 'admin' ? 5 : 4, 'student_enrollment_id' => 99999, 'assessment_id' => 99999, 'issued_by' => $actor->id])->assertStatus(303)->assertRedirect(route($actor->role.'.grades.show', $this->grade));
            $this->grade->refresh();
            $this->assertSame($before, $this->grade->only(array_keys($before)));
            $audit = $this->grade->audits()->latest('id')->firstOrFail();
            $this->assertSame('update', $audit->action);
            $this->assertSame($this->grade->version - 1, $audit->old_values['version']);
            $this->assertSame($this->grade->version, $audit->new_values['version']);
            $this->assertSame(5, $audit->new_values['weight']);
            $this->assertSame($this->input['category_id'], $audit->new_values['category_id']);
            $this->assertSame('Poprawiony temat', $audit->new_values['topic']);
            $this->assertSame('Nowy komentarz', $audit->new_values['comment']);
            $this->assertSame('2026-10-02', $audit->new_values['graded_on']);
            $this->assertSame($actor->id, $audit->actor_id);
            $this->assertSame('Korekta punktacji', $audit->reason);
        }
    }

    public function test_stale_version_and_no_op_do_not_add_audits_or_overwrite(): void
    {
        $url = route('teacher.grades.update', $this->grade);
        $edit = route('teacher.grades.edit', $this->grade);
        $this->actingAs($this->assignment->teacher)->patch($url, $this->input)->assertSessionHasNoErrors();
        $this->from($edit)->patch($url, [...$this->input, 'value' => 6])->assertRedirect($edit)->assertSessionHasErrors('conflict')->assertSessionHasInput('value', 6);
        $this->patch($url, [...$this->input, 'version' => 2])->assertSessionHasNoErrors();
        $this->assertSame(2, $this->grade->fresh()->version);
        $this->assertSame(4, $this->grade->fresh()->value);
        $this->assertDatabaseCount('grade_audits', 2);
    }

    public function test_co_teacher_can_edit_but_foreign_teacher_student_and_mismatched_assignment_cannot(): void
    {
        $url = route('teacher.grades.update', $this->grade);
        $this->patch($url, $this->input)->assertRedirect(route('login'));
        $this->actingAs($this->grade->enrollment->student)->patch($url, $this->input)->assertForbidden();
        $this->actingAs(User::factory()->teacher()->create())->patch($url, $this->input)->assertNotFound();
        $this->actingAs($this->assignment->teacher)->patch($url, [...$this->input, 'assignment_id' => TeacherAssignment::factory()->create()->id])->assertNotFound();
        $co = TeacherAssignment::factory()->create($this->assignment->only(['school_class_id', 'subject_id', 'term_id']));
        $this->actingAs($co->teacher)->patch($url, [...$this->input, 'assignment_id' => $co->id])->assertSessionHasNoErrors();
        $this->assertSame($this->assignment->teacher_id, $this->grade->fresh()->issued_by);
        $this->assignment->update(['revoked_at' => now()]);
        $this->actingAs($this->assignment->teacher)->patch($url, [...$this->input, 'version' => 2])->assertNotFound();
        $this->assertDatabaseCount('grade_audits', 2);
    }

    public function test_validation_and_closed_term_inactive_student_deleted_grade_prevent_changes(): void
    {
        $url = route('teacher.grades.update', $this->grade);
        $this->actingAs($this->assignment->teacher)->patch($url, [...$this->input, 'value' => 7, 'weight' => 6, 'version' => 0, 'reason' => 'x', 'graded_on' => '2026-11-01', 'topic' => '', 'category_id' => 99999])->assertSessionHasErrors(['value', 'weight', 'version', 'reason', 'graded_on', 'topic', 'category_id']);
        $this->grade->enrollment->student->update(['status' => 'inactive']);
        $this->patch($url, $this->input)->assertForbidden();
        $this->grade->enrollment->student->update(['status' => 'active']);
        $this->assignment->term->update(['closed_at' => now()]);
        foreach ([$this->assignment->teacher, User::factory()->admin()->create()] as $actor) {
            $this->actingAs($actor)->get(route($actor->role.'.grades.edit', $this->grade))->assertForbidden();
            $this->patch(route($actor->role.'.grades.update', $this->grade), $this->input)->assertForbidden();
        }
        $this->grade->delete();
        $this->patch(route('admin.grades.update', $this->grade), $this->input)->assertNotFound();
        $this->assertDatabaseCount('grade_audits', 1);
    }

    public function test_audit_insert_failure_rolls_back_the_correction(): void
    {
        $dispatcher = GradeAudit::getEventDispatcher();
        GradeAudit::setEventDispatcher(clone $dispatcher);
        GradeAudit::creating(function (GradeAudit $audit) {
            $audit->actor_id = 99999999;
        });
        try {
            app(GradeService::class)->update($this->assignment->teacher_id, $this->grade->id, 1, 'Korekta', ['value' => 4]);
            $this->fail('Expected audit failure');
        } catch (QueryException $e) {
            $this->assertSame('23000', $e->errorInfo[0]);
        } finally {
            GradeAudit::setEventDispatcher($dispatcher);
        }
        $this->assertSame(1, $this->grade->fresh()->version);
        $this->assertSame(3, $this->grade->fresh()->value);
        $this->assertDatabaseCount('grade_audits', 1);
    }
}
