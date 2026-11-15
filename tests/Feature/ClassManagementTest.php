<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Grade;
use App\Models\GradeAudit;
use App\Models\SchoolClass;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Services\School\EnrollmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClassManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private SchoolClass $class;

    private SchoolClass $target;

    private StudentEnrollment $enrollment;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.password' => 'only-in-test-fixture', 'demo.date' => '2026-11-15']);
        $this->seed();
        $this->admin = User::where('role', 'admin')->firstOrFail();
        $grade = Grade::where('graded_on', '>=', '2026-09-01')->firstOrFail();
        $this->enrollment = $grade->enrollment;
        $this->class = $this->enrollment->schoolClass;
        $this->target = SchoolClass::factory()->create(['academic_year_id' => $this->class->academic_year_id, 'name' => 'Transfer']);
    }

    private function url(?SchoolClass $class = null): string
    {
        return '/admin/classes/'.($class ?? $this->class)->id;
    }

    public function test_only_admin_can_reach_any_class_or_membership_endpoint(): void
    {
        $routes = [['get', '/admin/classes', []], ['post', '/admin/classes', []], ['patch', $this->url(), []], ['delete', $this->url(), ['confirmed' => true]], ['post', $this->url().'/enrollments', []], ['patch', $this->url().'/enrollments/'.$this->enrollment->id, []]];
        $before = [SchoolClass::count(), StudentEnrollment::count()];
        foreach ($routes as [$method, $url, $data]) {
            $this->{$method}($url, $data)->assertRedirect('/login');
        }
        foreach (['student', 'teacher'] as $role) {
            $this->actingAs(User::where('role', $role)->firstOrFail());
            foreach ($routes as [$method, $url, $data]) {
                $this->{$method}($url, $data)->assertForbidden();
            }
        }
        $this->assertSame($before, [SchoolClass::count(), StudentEnrollment::count()]);
    }

    public function test_listing_has_allowlisted_props_scoped_selection_and_active_student_options(): void
    {
        $inactive = User::factory()->create(['status' => 'inactive']);
        $this->actingAs($this->admin)->get('/admin/classes?academic_year_id='.$this->class->academic_year_id.'&selected_class_id='.$this->class->id)
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('admin/classes/index')
            ->where('selectedClass.id', (string) $this->class->id)->missing('selectedClass.enrollments')
            ->has('enrollments.data')->where('students', fn ($students) => collect($students)->every(fn ($option) => array_keys((array) $option) === ['id', 'label'] && $option['id'] !== (string) $inactive->id)));
        $otherYear = AcademicYear::factory()->create();
        $this->get('/admin/classes?academic_year_id='.$otherYear->id.'&selected_class_id='.$this->class->id)->assertNotFound();
        $this->patch($this->url($this->target).'/enrollments/'.$this->enrollment->id, ['starts_on' => '2026-09-01'])->assertNotFound();
    }

    public function test_create_rename_and_archive_preserve_year_and_reserve_archived_names(): void
    {
        $year = $this->class->academic_year_id;
        $this->actingAs($this->admin)->post('/admin/classes', ['academic_year_id' => $year, 'name' => 'New', 'deleted_at' => now()])->assertSessionHasNoErrors()->assertRedirect();
        $class = SchoolClass::where('name', 'New')->firstOrFail();
        $this->assertNull($class->deleted_at);
        $this->patch($this->url($class), ['name' => 'Renamed', 'academic_year_id' => AcademicYear::factory()->create()->id, 'archived' => true])->assertSessionHasNoErrors();
        $this->assertSame($year, $class->fresh()->academic_year_id);
        $this->delete($this->url($class), ['confirmed' => false])->assertSessionHasErrors('confirmed');
        $this->delete($this->url($class), ['confirmed' => true])->assertSessionHasNoErrors();
        $this->assertSoftDeleted($class);
        $this->post('/admin/classes', ['academic_year_id' => $year, 'name' => 'Renamed'])->assertSessionHasErrors('name');
        $this->patch($this->url($class), ['name' => 'Again'])->assertNotFound();
    }

    public function test_active_relations_prevent_archive_and_ended_history_survives_archive(): void
    {
        $this->actingAs($this->admin)->delete($this->url(), ['confirmed' => true])->assertSessionHasErrors('form');
        $this->assertNotSoftDeleted($this->class);
        $this->class->enrollments()->update(['ends_on' => '2026-11-14']);
        $this->delete($this->url(), ['confirmed' => true])->assertSessionHasErrors('form');
        $this->class->assignments()->update(['revoked_at' => now()]);
        $gradeId = Grade::where('student_enrollment_id', $this->enrollment->id)->firstOrFail()->id;
        $this->delete($this->url(), ['confirmed' => true])->assertSessionHasNoErrors();
        $this->assertSoftDeleted($this->class);
        $this->assertSame($this->class->id, Grade::findOrFail($gradeId)->enrollment->schoolClass->id);
    }

    public function test_transfer_keeps_grade_and_audit_references_and_uses_adjacent_dates(): void
    {
        $grades = Grade::withTrashed()->where('student_enrollment_id', $this->enrollment->id)->pluck('id')->all();
        $auditCount = GradeAudit::count();
        $this->actingAs($this->admin)->post($this->url($this->target).'/enrollments', [
            'student_id' => $this->enrollment->student_id, 'starts_on' => '2026-11-15', 'transfer_from_enrollment_id' => $this->enrollment->id,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('2026-11-14', $this->enrollment->fresh()->ends_on->toDateString());
        $this->assertDatabaseHas('student_enrollments', ['student_id' => $this->enrollment->student_id, 'school_class_id' => $this->target->id, 'starts_on' => '2026-11-15']);
        $this->assertSame($grades, Grade::withTrashed()->where('student_enrollment_id', $this->enrollment->id)->pluck('id')->all());
        $this->assertSame($auditCount, GradeAudit::count());
    }

    public function test_invalid_transfer_or_insert_failure_rolls_back_source_end_date(): void
    {
        $before = $this->enrollment->ends_on;
        $this->actingAs($this->admin)->post($this->url($this->target).'/enrollments', ['student_id' => $this->enrollment->student_id, 'starts_on' => '2026-09-02', 'transfer_from_enrollment_id' => $this->enrollment->id])->assertSessionHasErrors('ends_on');
        $this->assertEquals($before, $this->enrollment->fresh()->ends_on);
        StudentEnrollment::creating(fn () => throw new \RuntimeException('fixture failure'));
        try {
            try {
                app(EnrollmentService::class)->enroll($this->enrollment->student_id, $this->target->id, '2026-11-15', null, $this->enrollment->id, $this->admin->id);
                $this->fail('Expected insert failure');
            } catch (\RuntimeException $exception) {
                $this->assertSame('fixture failure', $exception->getMessage());
                $this->assertEquals($before, $this->enrollment->fresh()->ends_on);
            }
        } finally {
            StudentEnrollment::flushEventListeners();
        }
    }

    public function test_membership_dates_reject_overlap_and_all_grades_including_deleted(): void
    {
        $url = $this->url().'/enrollments/'.$this->enrollment->id;
        $this->actingAs($this->admin)->patch($url, ['starts_on' => '2026-09-01', 'ends_on' => '2026-09-02'])->assertSessionHasErrors('ends_on');
        Grade::where('student_enrollment_id', $this->enrollment->id)->update(['deleted_at' => now()]);
        $this->patch($url, ['starts_on' => '2026-09-01', 'ends_on' => '2026-09-02'])->assertSessionHasErrors('ends_on');
        $this->post($this->url($this->target).'/enrollments', ['student_id' => $this->enrollment->student_id, 'starts_on' => '2026-11-15'])->assertSessionHasErrors('starts_on');
        $this->patch($url, ['starts_on' => '2026-09-01', 'ends_on' => '2026-11-14', 'student_id' => $this->admin->id, 'school_class_id' => $this->target->id])->assertSessionHasNoErrors();
        $this->assertSame($this->class->id, $this->enrollment->fresh()->school_class_id);
        $this->assertNotSame($this->admin->id, $this->enrollment->fresh()->student_id);
        $this->enrollment->student->update(['status' => 'inactive']);
        $this->patch($url, ['starts_on' => '2026-09-01', 'ends_on' => '2026-11-13'])->assertSessionHasNoErrors();
    }

    public function test_invalid_types_year_dates_and_transfer_owner_do_not_write(): void
    {
        $this->actingAs($this->admin);
        $before = StudentEnrollment::count();
        $this->post('/admin/classes', ['academic_year_id' => ['invalid'], 'name' => 'Test'])->assertSessionHasErrors('academic_year_id');
        $this->post($this->url($this->target).'/enrollments', ['student_id' => ['invalid'], 'starts_on' => '2026-11-15', 'transfer_from_enrollment_id' => $this->enrollment->id])->assertSessionHasErrors('student_id');
        $student = User::factory()->create();
        $this->post($this->url($this->target).'/enrollments', ['student_id' => $student->id, 'starts_on' => '2026-08-31'])->assertSessionHasErrors('starts_on');
        $this->post($this->url($this->target).'/enrollments', ['student_id' => $student->id, 'starts_on' => '2026-09-01', 'ends_on' => '2027-09-01'])->assertSessionHasErrors('starts_on');
        $this->post($this->url($this->target).'/enrollments', ['student_id' => $student->id, 'starts_on' => '2026-11-15', 'transfer_from_enrollment_id' => $this->enrollment->id])->assertSessionHasErrors('transfer_from_enrollment_id');
        $this->assertSame($before, StudentEnrollment::count());
    }

    public function test_bad_filters_and_out_of_range_pages_have_canonical_redirects(): void
    {
        SchoolClass::factory()->create(['academic_year_id' => $this->class->academic_year_id, 'name' => '0']);
        $this->actingAs($this->admin)->get('/admin/classes?q=0')->assertInertia(fn (Assert $page) => $page->has('classes.data', 1)->where('classes.data.0.name', '0'));
        $this->get('/admin/classes?q=1&per_page=13&page=bad')->assertStatus(303)->assertRedirect('/admin/classes?q=1')->assertSessionHasErrors(['page', 'per_page']);
        $response = $this->get('/admin/classes?page=99999');
        $response->assertStatus(303);
        $this->get($response->headers->get('Location'))->assertOk();
        $response = $this->get('/admin/classes?selected_class_id='.$this->class->id.'&enrollment_page=99999');
        $response->assertStatus(303);
        $this->get($response->headers->get('Location'))->assertOk();
    }
}
