<?php

namespace Tests\Feature;

use App\Models\Grade;
use App\Models\GradeAudit;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Models\Term;
use App\Models\User;
use App\Services\Grades\GradeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GradeHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleted_grade_preserves_full_create_update_delete_and_deleted_actor(): void
    {
        $g = Grade::factory()->create(['comment' => 'Przed', 'value' => 3]);
        $g = app(GradeService::class)->update($g->issued_by, $g->id, $g->version, 'Korekta punktów', ['value' => 4, 'weight' => 5, 'comment' => '<script>tekst</script>', 'topic' => 'Po korekcie']);
        app(GradeService::class)->delete($g->issued_by, $g->id, $g->version, 'Błędny wpis');
        $g->issuer->delete();
        $g->assessment->delete();
        $g->assessment->subject->delete();
        $g->assessment->schoolClass->delete();
        $g->enrollment->update(['ends_on' => $g->graded_on]);
        $g->assessment->term->update(['closed_at' => now()]);
        foreach ([$g->enrollment->student, User::factory()->admin()->create()] as $actor) {
            $this->actingAs($actor)->get(route($actor->role.'.grades.history', $g))->assertOk()
                ->assertInertia(fn (Assert $p) => $p->component('shared/history/index')
                    ->has('audits.data', 3)->where('audits.meta.total', 3)
                    ->where('audits.data.0.action', 'delete')->where('audits.data.1.action', 'update')->where('audits.data.2.action', 'create')
                    ->where('audits.data.2.oldValues', null)->where('audits.data.1.oldValues.value', 3)
                    ->where('audits.data.1.newValues.value', 4)->where('audits.data.1.newValues.weight', 5)
                    ->where('audits.data.1.oldValues.comment', 'Przed')->where('audits.data.1.newValues.comment', '<script>tekst</script>')
                    ->where('audits.data.0.newValues.deleted_at', fn ($v) => is_string($v))
                    ->where('audits.data.0.oldValues.deleted_at', null)->where('audits.data.0.actor.label', $g->issuer->name)
                    ->missing('audits.data.0.actor.email')->missing('grade.student.email'));
            $this->get(route($actor->role.'.history.index'))->assertOk()->assertInertia(fn (Assert $p) => $p->has('audits.data', 3));
        }
        $foreign = User::factory()->create();
        $this->actingAs($foreign)->get(route('student.grades.history', $g))->assertNotFound();
        $this->get(route('student.history.index', ['grade_id' => $g->id]))->assertNotFound();
        $this->get(route('student.history.index'))->assertInertia(fn (Assert $p) => $p->has('audits.data', 0)->has('options.subjects', 0)->has('options.terms', 0));
    }

    public function test_teacher_uses_exact_current_assignment_including_previous_authors(): void
    {
        $g = Grade::factory()->create();
        $teacher = User::factory()->teacher()->create();
        $assignment = TeacherAssignment::factory()->create(['teacher_id' => $teacher->id,
            'school_class_id' => $g->assessment->school_class_id, 'subject_id' => $g->assessment->subject_id, 'term_id' => $g->assessment->term_id]);
        $this->actingAs($teacher)->get(route('teacher.history.index'))->assertInertia(fn (Assert $p) => $p->has('audits.data', 1)->where('audits.data.0.actor.id', (string) $g->issued_by));
        $this->get(route('teacher.grades.history', $g))->assertOk();
        foreach (['subject_id' => Subject::factory()->create()->id, 'term_id' => Term::factory()->create()->id, 'school_class_id' => SchoolClass::factory()->create()->id] as $field => $id) {
            $original = $assignment->$field;
            $assignment->update([$field => $id]);
            $this->get(route('teacher.grades.history', $g))->assertNotFound();
            $this->get(route('teacher.history.index'))->assertInertia(fn (Assert $p) => $p->has('audits.data', 0)->has('options.subjects', 0)->has('options.terms', 0));
            $assignment->update([$field => $original]);
        }
        $assignment->update(['revoked_at' => now()]);
        $this->get(route('teacher.grades.history', $g))->assertNotFound();
        $this->get(route('teacher.history.index'))->assertInertia(fn (Assert $p) => $p->has('audits.data', 0));
        $g->issuer->update(['role' => 'teacher']);
        $this->actingAs($g->issuer)->get(route('teacher.grades.history', $g))->assertNotFound();
    }

    public function test_sorting_ties_pagination_and_filters_are_stable_and_scoped(): void
    {
        $g = Grade::factory()->create();
        foreach (range(1, 12) as $i) {
            $g = app(GradeService::class)->update($g->issued_by, $g->id, $g->version, 'Korekta '.$i, ['value' => $i % 2 ? 5 : 4]);
        }
        Grade::factory()->create();
        $params = ['action' => 'update', 'term_id' => $g->assessment->term_id, 'subject_id' => $g->assessment->subject_id];
        $url = route('student.grades.history', $g);
        $this->actingAs($g->enrollment->student)->get($url.'?'.http_build_query($params))->assertInertia(fn (Assert $p) => $p
            ->has('audits.data', 10)->where('audits.meta.total', 12)->where('audits.data.0.reason', 'Korekta 12')->where('audits.data.9.reason', 'Korekta 3')
            ->where('audits.links.1.url', fn ($v) => str_contains($v, 'action=update') && str_contains($v, 'page=2')));
        $this->get($url.'?'.http_build_query([...$params, 'page' => 2]))->assertInertia(fn (Assert $p) => $p->has('audits.data', 2)->where('audits.data.0.reason', 'Korekta 2'));
        $this->get($url.'?action=delete')->assertInertia(fn (Assert $p) => $p->has('audits.data', 0));
        $this->get($url.'?action=update&page=999')->assertStatus(303)->assertRedirectContains('page=2');
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('admin.history.index', ['student_enrollment_id' => $g->student_enrollment_id, 'per_page' => 25]))
            ->assertInertia(fn (Assert $p) => $p->has('audits.data', 13)->where('audits.meta.total', 13));
        $other = Grade::factory()->create();
        $this->get(route('admin.grades.history', [$g, 'grade_id' => $other->id]))->assertNotFound();
        $this->get(route('admin.grades.history', [$g, 'student_enrollment_id' => $other->student_enrollment_id]))->assertNotFound();
    }

    public function test_date_filters_use_warsaw_day_boundaries_including_dst(): void
    {
        $g = Grade::factory()->create();
        // Warsaw's DST transition day has 25 hours. Last boundary is exclusive.
        $template = $g->audits()->firstOrFail()->getAttributes();
        DB::table('grade_audits')->where('grade_id', $g->id)->update(['occurred_at' => '2026-10-24 21:59:59']);
        foreach (['2026-10-24 22:00:00', '2026-10-25 22:59:59', '2026-10-25 23:00:00'] as $i => $time) {
            unset($template['id']);
            DB::table('grade_audits')->insert([...$template, 'occurred_at' => $time, 'grade_version' => $i + 2]);
        }
        $this->actingAs($g->enrollment->student)->get(route('student.history.index', ['date_from' => '2026-10-25', 'date_to' => '2026-10-25']))
            ->assertInertia(fn (Assert $p) => $p->has('audits.data', 2)->where('audits.meta.total', 2));
    }

    public function test_snapshot_output_has_only_the_explicit_contract_keys(): void
    {
        $g = Grade::factory()->create();
        $response = $this->actingAs($g->enrollment->student)->get(route('student.history.index'))->assertOk();
        $response->assertInertia(fn (Assert $p) => $p->missing('audits.data.0.newValues.private_note')->where('audits.data.0.newValues.id', (string) $g->id));
        $snapshot = $response->viewData('page')['props']['audits']['data'][0]['newValues'];
        $this->assertEqualsCanonicalizing(['schema_version', 'id', 'student_enrollment_id', 'student_id', 'assessment_id', 'school_class_id', 'subject_id', 'term_id', 'category_id', 'value', 'weight', 'topic', 'comment', 'graded_on', 'issued_by', 'version', 'deleted_at'], array_keys($snapshot));
    }

    public function test_invalid_filters_authorization_guest_role_and_no_mutation_routes(): void
    {
        $g = Grade::factory()->create();
        $this->get('/student/history')->assertRedirect(route('login'));
        $this->actingAs($g->enrollment->student)->get('/teacher/history')->assertForbidden();
        $this->get('/admin/history')->assertForbidden();
        foreach ([['action' => 'bad'], ['action' => ['create']], ['per_page' => 10000], ['page' => 0], ['date_from' => 'invalid'], ['date_from' => '2026-11-10', 'date_to' => '2026-11-01'], ['term_id' => 999999], ['subject_id' => 999999], ['student_id' => 999999], ['student_enrollment_id' => $g->student_enrollment_id]] as $params) {
            $this->get(route('student.history.index', $params))->assertStatus(303)->assertRedirect(route('student.history.index'))->assertSessionHasErrors();
        }
        $this->actingAs(User::factory()->create())->get(route('student.grades.history', [$g, 'action' => ['bad']]))->assertNotFound();
        $this->get('/student/grades/bad/history')->assertNotFound();
        $this->get('/student/grades/999999/history')->assertNotFound();
        $this->actingAs(User::factory()->create(['status' => 'inactive']))->get('/student/history')->assertRedirect(route('login'));
        $this->actingAs($g->enrollment->student)->delete('/student/history')->assertStatus(405);
        $this->assertSame(1, GradeAudit::count());
    }
}
