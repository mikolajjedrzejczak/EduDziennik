<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\StudentEnrollment;
use App\Models\TeacherAssignment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EnrollmentEffectiveEndTest extends TestCase
{
    use RefreshDatabase;

    public static function archiveDates(): array
    {
        return [
            'expired implicit end' => ['2024-09-01', '2025-08-31', null, '2026-11-17', true],
            'inclusive implicit end' => ['2026-09-01', '2027-08-31', null, '2027-08-31', false],
            'day after implicit end' => ['2026-09-01', '2027-08-31', null, '2027-09-01', true],
            'active implicit end' => ['2026-09-01', '2027-08-31', null, '2026-11-17', false],
            'future implicit end' => ['2027-09-01', '2028-08-31', null, '2026-11-17', false],
            'inclusive explicit end' => ['2026-09-01', '2027-08-31', '2026-11-17', '2026-11-17', false],
            'expired explicit end' => ['2026-09-01', '2027-08-31', '2026-11-16', '2026-11-17', true],
            'future explicit end' => ['2027-09-01', '2028-08-31', '2027-10-01', '2026-11-17', false],
        ];
    }

    #[DataProvider('archiveDates')]
    public function test_archive_uses_the_effective_end_and_preserves_memberships(string $start, string $end, ?string $explicitEnd, string $today, bool $allowed): void
    {
        $this->travelTo(CarbonImmutable::parse($today.' 12:00:00', 'Europe/Warsaw'));
        $year = AcademicYear::factory()->create(['starts_on' => $start, 'ends_on' => $end]);
        $class = SchoolClass::factory()->create(['academic_year_id' => $year->id]);
        $enrollment = StudentEnrollment::factory()->create(['school_class_id' => $class->id, 'starts_on' => $start, 'ends_on' => $explicitEnd]);

        $response = $this->actingAs(User::factory()->admin()->create())->delete(route('admin.classes.destroy', $class), ['confirmed' => true]);
        if ($allowed) {
            $response->assertSessionHasNoErrors();
            $this->assertSoftDeleted($class);
        } else {
            $response->assertSessionHasErrors('form');
            $this->assertNotSoftDeleted($class);
        }
        $this->assertDatabaseHas('student_enrollments', ['id' => $enrollment->id, 'ends_on' => $explicitEnd]);
    }

    public function test_unrevoked_assignment_still_blocks_archiving_an_expired_class(): void
    {
        $year = AcademicYear::factory()->create(['starts_on' => '2024-09-01', 'ends_on' => '2025-08-31']);
        $class = SchoolClass::factory()->create(['academic_year_id' => $year->id]);
        StudentEnrollment::factory()->create(['school_class_id' => $class->id, 'starts_on' => '2024-09-01', 'ends_on' => null]);
        TeacherAssignment::factory()->create(['school_class_id' => $class->id]);

        $this->actingAs(User::factory()->admin()->create())->delete(route('admin.classes.destroy', $class), ['confirmed' => true])->assertSessionHasErrors('form');
        $this->assertNotSoftDeleted($class);
    }

    public static function boundaryDates(): array
    {
        return [
            'last day in Warsaw' => ['2027-08-31 21:59:59 UTC', null, false],
            'next day in Warsaw' => ['2027-08-31 22:00:00 UTC', null, true],
            'explicit end today' => ['2026-11-15 12:00:00 UTC', '2026-11-15', false],
            'day after explicit end' => ['2026-11-16 12:00:00 UTC', '2026-11-15', true],
        ];
    }

    #[DataProvider('boundaryDates')]
    public function test_user_summary_and_grade_dtos_agree_on_the_inclusive_year_end(string $now, ?string $explicitEnd, bool $historical): void
    {
        $grade = Grade::factory()->create();
        $enrollment = $grade->enrollment;
        $enrollment->update(['ends_on' => $explicitEnd]);
        $assessment = $grade->assessment;
        $assignment = TeacherAssignment::factory()->create([
            'school_class_id' => $assessment->school_class_id, 'subject_id' => $assessment->subject_id, 'term_id' => $assessment->term_id,
        ]);
        $admin = User::factory()->admin()->create();
        $this->travelTo(CarbonImmutable::parse($now));

        $this->actingAs($admin)->get(route('admin.users.index', ['q' => $enrollment->student->email]))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->has('users.data', 1)->where('users.data.0.relationSummary', $historical ? '' : $assessment->schoolClass->name));
        $this->get(route('admin.gradebooks.show', $assignment))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('students.data.0.student.historical', $historical)
                ->where('students.data.0.grades.0.student.historical', $historical));
        $this->get(route('admin.students.show', [$assignment, $enrollment]))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('student.historical', $historical));
        $this->get(route('admin.grades.show', $grade))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('grade.student.historical', $historical));
        $this->get(route('admin.grades.create', ['assignment_id' => $assignment->id]))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('students.0.historical', $historical));
    }

    public function test_future_enrollment_is_not_a_current_class_label(): void
    {
        $enrollment = StudentEnrollment::factory()->create(['starts_on' => '2027-01-01', 'ends_on' => null]);
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.users.index', ['q' => $enrollment->student->email]))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('users.data.0.relationSummary', ''));
    }
}
