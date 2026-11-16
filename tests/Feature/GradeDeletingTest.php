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

class GradeDeletingTest extends TestCase
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
        $this->grade = app(GradeService::class)->create($this->assignment->teacher_id, $enrollment->id, $assessment->id, ['value' => 3, 'weight' => 1]);
        $this->input = ['assignment_id' => (string) $this->assignment->id, 'version' => 1, 'reason' => 'Błędny wpis', 'confirmed' => true];
    }

    public function test_delete_preserves_snapshots_history_and_recalculates_average(): void
    {
        foreach ([[4, 2], [5, 1], [5, 3]] as [$value, $weight]) {
            $assessment = Assessment::factory()->create([...$this->grade->assessment->only(['school_class_id', 'subject_id', 'term_id', 'category_id']), 'graded_on' => '2026-10-01']);
            app(GradeService::class)->create($this->assignment->teacher_id, $this->grade->student_enrollment_id, $assessment->id, ['value' => $value, 'weight' => $weight]);
        }
        $this->actingAs($this->assignment->teacher)->delete(route('teacher.grades.destroy', $this->grade), $this->input)
            ->assertStatus(303)->assertRedirect(route('teacher.gradebooks.show', $this->assignment));
        $this->assertSoftDeleted($this->grade);
        $audit = $this->grade->audits()->latest('id')->firstOrFail();
        $this->assertSame('delete', $audit->action);
        $this->assertSame('Błędny wpis', $audit->reason);
        $this->assertSame($this->assignment->teacher_id, $audit->actor_id);
        $this->assertSame(1, $audit->old_values['version']);
        $this->assertNull($audit->old_values['deleted_at']);
        $this->assertSame(2, $audit->new_values['version']);
        $this->assertNotNull($audit->new_values['deleted_at']);
        $this->get(route('teacher.grades.show', $this->grade))->assertInertia(fn (Assert $p) => $p->where('summary.average', '4.67')->where('grade.can.delete', false));
        $this->actingAs($this->grade->enrollment->student)->get(route('student.grades.history', $this->grade))->assertOk();
        $this->actingAs($this->assignment->teacher)->delete(route('teacher.grades.destroy', $this->grade), $this->input)->assertNotFound();
        $this->assertSame(2, $this->grade->audits()->count());
        $this->post(route('teacher.grades.store'), [...$this->grade->only(['assessment_id', 'student_enrollment_id', 'value', 'weight', 'category_id', 'topic', 'comment']), 'graded_on' => '2026-10-01', 'assignment_id' => $this->assignment->id])->assertSessionHasErrors('form');
    }

    public function test_admin_can_delete_and_forged_fields_do_not_change_snapshots(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->delete(route('admin.grades.destroy', $this->grade), [...$this->input, 'value' => 6, 'issued_by' => $admin->id])->assertStatus(303);
        $this->assertSame(3, $this->grade->fresh()->value);
        $this->assertSame($this->assignment->teacher_id, $this->grade->fresh()->issued_by);
    }

    public function test_validation_stale_version_and_lost_assignment_do_not_delete(): void
    {
        $url = route('teacher.grades.destroy', $this->grade);
        $this->actingAs($this->assignment->teacher)->delete($url, [...$this->input, 'confirmed' => false, 'reason' => 'x', 'version' => 0])->assertSessionHasErrors(['confirmed', 'reason', 'version']);
        $this->delete($url, [...$this->input, 'version' => 2])->assertSessionHasErrors('conflict');
        $this->delete($url, [...$this->input, 'assignment_id' => TeacherAssignment::factory()->create()->id])->assertNotFound();
        $this->assignment->update(['revoked_at' => now()]);
        $this->delete($url, $this->input)->assertNotFound();
        $this->assertNull($this->grade->fresh()->deleted_at);
        $this->assertDatabaseCount('grade_audits', 1);
    }

    public function test_guest_student_foreign_teacher_and_closed_term_cannot_delete(): void
    {
        $url = route('teacher.grades.destroy', $this->grade);
        $this->delete($url, $this->input)->assertRedirect(route('login'));
        $this->actingAs($this->grade->enrollment->student)->delete($url, $this->input)->assertForbidden();
        $this->actingAs(User::factory()->teacher()->create())->delete($url, $this->input)->assertNotFound();
        $this->grade->enrollment->student->update(['status' => 'inactive']);
        $this->actingAs($this->assignment->teacher)->delete($url, $this->input)->assertForbidden();
        $this->grade->enrollment->student->update(['status' => 'active']);
        $this->assignment->term->update(['closed_at' => now()]);
        foreach ([$this->assignment->teacher, User::factory()->admin()->create()] as $actor) {
            $this->actingAs($actor)->delete(route($actor->role.'.grades.destroy', $this->grade), $this->input)->assertForbidden();
        }
        $this->assertNull($this->grade->fresh()->deleted_at);
        $this->assertDatabaseCount('grade_audits', 1);
    }

    public function test_audit_failure_rolls_back_deletion_and_version(): void
    {
        $dispatcher = GradeAudit::getEventDispatcher();
        GradeAudit::setEventDispatcher(clone $dispatcher);
        GradeAudit::creating(function (GradeAudit $audit) {
            $audit->actor_id = 99999999;
        });
        try {
            app(GradeService::class)->delete($this->assignment->teacher_id, $this->grade->id, 1, 'Błędny wpis');
            $this->fail('Expected audit failure');
        } catch (QueryException $e) {
            $this->assertSame('23000', $e->errorInfo[0]);
        } finally {
            GradeAudit::setEventDispatcher($dispatcher);
        }
        $this->assertNull($this->grade->fresh()->deleted_at);
        $this->assertSame(1, $this->grade->fresh()->version);
        $this->assertDatabaseCount('grade_audits', 1);
    }
}
