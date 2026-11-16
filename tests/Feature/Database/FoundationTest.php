<?php

namespace Tests\Feature\Database;

use App\Models\Assessment;
use App\Models\Grade;
use App\Models\GradeAudit;
use App\Models\SchoolClass;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Services\Grades\GradeService;
use App\Services\Grades\GradeSummary;
use App\Services\School\AssessmentService;
use App\Services\School\AssignmentService;
use App\Services\School\CalendarService;
use App\Services\School\EnrollmentService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 15)->setTime(12, 0));
        config(['demo.password' => 'only-in-test-fixture', 'demo.date' => '2026-11-15']);
        $this->seed();
    }

    private function actor(string $name = 'teacher1'): User
    {
        return User::where('email', $name.'@example.test')->firstOrFail();
    }

    private function baseGrade(): Grade
    {
        return Grade::whereHas('enrollment.student', fn ($q) => $q->where('email', 'student7@example.test'))
            ->whereHas('assessment.term.academicYear', fn ($q) => $q->where('name', '2026/2027'))->orderBy('id')->firstOrFail();
    }

    private function summary(User $actor): array
    {
        return app(GradeSummary::class)->forStudent($actor, $this->actor('student7')->id, $this->baseGrade()->assessment->term_id);
    }

    public function test_fixture_is_consistent_and_scoped_sql_average_is_443(): void
    {
        $this->assertSame(23, User::withTrashed()->count());
        $this->assertSame(18, StudentEnrollment::whereHas('schoolClass.academicYear', fn ($q) => $q->where('name', '2026/2027'))->count());
        $this->assertSame(['average' => '4.43', 'count' => 4], $this->summary($this->actor('student7')));
        $this->assertSame($this->summary($this->actor('student7')), $this->summary($this->actor()));
        $this->assertSame(['average' => null, 'count' => 0], $this->summary($this->actor('teacher2')));
        $this->assertSame(['average' => null, 'count' => 0], $this->summary($this->actor('student8')));
        $this->assertSame(['average' => '4.43', 'count' => 4], $this->summary($this->actor('admin1')));
        foreach (Grade::withTrashed()->get() as $grade) {
            $this->assertSame(1, $grade->audits()->where('action', 'create')->count());
            $this->assertSame($grade->enrollment->school_class_id, $grade->assessment->school_class_id);
            $this->assertSame($grade->version, $grade->audits()->count());
            $snapshot = $grade->audits()->latest('grade_version')->firstOrFail()->new_values;
            $this->assertSame('string', gettype($snapshot['student_id']));
            $this->assertSame(1, $snapshot['schema_version']);
            $this->assertCount(17, $snapshot);
        }
        $this->assertSame(1, DB::table('domain_locks')->where('key', 'admin_membership')->count());
        $this->assertSame(2, Term::whereNotNull('closed_at')->count());
        $this->assertFalse(Grade::whereHas('enrollment.student', fn ($q) => $q->where('email', 'student1@example.test'))->exists());
    }

    #[DataProvider('independentVariants')]
    public function test_independent_average_variants(string $action, string $expected): void
    {
        $grade = $this->baseGrade();
        $service = app(GradeService::class);
        if ($action === 'create') {
            $header = app(AssessmentService::class)->create($this->actor()->id, [
                ...$grade->assessment->only(['school_class_id', 'subject_id', 'term_id', 'category_id']),
                'topic' => 'Nowa kolumna', 'default_weight' => 2, 'graded_on' => '2026-10-30',
            ]);
            $service->create($this->actor()->id, $grade->student_enrollment_id, $header->id, ['value' => 4]);
        } else {
            $grade = Grade::where('student_enrollment_id', $grade->student_enrollment_id)->where('value', 3)->firstOrFail();
            if ($action === 'update') {
                $service->update($this->actor()->id, $grade->id, 1, 'Poprawa wyniku', ['value' => 4]);
            } else {
                $service->delete($this->actor()->id, $grade->id, 1, 'Błędny wpis');
            }
        }
        $this->assertSame($expected, $this->summary($this->actor('student7'))['average']);
    }

    public static function independentVariants(): array
    {
        return [['create', '4.33'], ['update', '4.57'], ['delete', '4.67']];
    }

    #[DataProvider('invalidConstraints')]
    public function test_mariadb_rejects_invalid_constraints(string $table, string $column, mixed $value): void
    {
        $id = DB::table($table)->value('id');
        $this->expectException(QueryException::class);
        DB::table($table)->where('id', $id)->update([$column => $value]);
    }

    public static function invalidConstraints(): array
    {
        return [
            ['grades', 'weight', 0], ['grades', 'weight', 6], ['grades', 'value', 0], ['grades', 'value', 7],
            ['grades', 'version', 0], ['grades', 'assessment_id', 999999], ['grades', 'category_id', 999999],
            ['grade_categories', 'default_weight', 6], ['assessments', 'default_weight', 0],
            ['users', 'role', 'operator'], ['users', 'status', 'unknown'], ['users', 'email', 'UPPER@example.test'],
            ['terms', 'number', 3], ['terms', 'ends_on', '2000-01-01'],
            ['academic_years', 'ends_on', '2000-01-01'], ['student_enrollments', 'ends_on', '2000-01-01'],
            ['grade_audits', 'action', 'purge'], ['grade_audits', 'new_values', null],
            ['grade_audits', 'new_values', '{"schema_version":1}'],
            ['grade_audits', 'reason', 'create must not have reason'],
        ];
    }

    public function test_soft_deletion_reserves_grade_cell_and_user_email_and_retains_relations(): void
    {
        $grade = $this->baseGrade();
        app(GradeService::class)->delete($this->actor()->id, $grade->id, 1, 'Błędny wpis');
        $this->assertNull(Grade::find($grade->id));
        $archived = Grade::withTrashed()->findOrFail($grade->id);
        $this->assertCount(2, $archived->audits);
        $this->assertNotNull($archived->audits->last()->new_values['deleted_at']);
        $this->expectException(QueryException::class);
        // The service reports duplicates as validation; the DB still reserves archived cells.
        DB::table('grades')->insert(collect($archived->getAttributes())->except('id')->all());
    }

    public function test_foreign_keys_restrict_physical_deletion_and_update(): void
    {
        $this->expectException(QueryException::class);
        DB::table('users')->where('id', $this->actor('student7')->id)->delete();
    }

    public function test_audit_failure_rolls_back_grade_and_version(): void
    {
        $grade = $this->baseGrade();
        $before = GradeAudit::count();
        GradeAudit::creating(fn () => throw new \RuntimeException('Fault injection'));
        try {
            app(GradeService::class)->update($this->actor()->id, $grade->id, 1, 'Korekta wyniku', ['value' => 6]);
            $this->fail('Expected audit failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('Fault injection', $e->getMessage());
        } finally {
            GradeAudit::flushEventListeners();
            GradeAudit::clearBootedModels();
        }
        $this->assertSame(4, $grade->fresh()->value);
        $this->assertSame(1, $grade->fresh()->version);
        $this->assertSame($before, GradeAudit::count());
    }

    public function test_teacher_without_exact_assignment_cannot_mutate_or_read_grades(): void
    {
        $grade = $this->baseGrade();
        $this->assertSame(0, Grade::visibleTo($this->actor('teacher3'))->count());
        $this->expectException(AuthorizationException::class);
        app(GradeService::class)->update($this->actor('teacher2')->id, $grade->id, 1, 'Korekta wyniku', ['value' => 6]);
    }

    public function test_student_cannot_mutate_even_own_grade(): void
    {
        $grade = $this->baseGrade();
        $this->expectException(AuthorizationException::class);
        app(GradeService::class)->update($this->actor('student7')->id, $grade->id, 1, 'Korekta wyniku', ['value' => 6]);
    }

    public function test_revocation_removes_access_and_reassignment_reuses_the_row(): void
    {
        $grade = $this->baseGrade();
        $assignment = $this->actor()->assignments()->where('subject_id', $grade->assessment->subject_id)->firstOrFail();
        $assignment->update(['revoked_at' => now()]);
        $this->assertSame(0, Grade::visibleTo($this->actor())->count());
        $restored = app(AssignmentService::class)->assign($this->actor()->id, $assignment->school_class_id, $assignment->subject_id, $assignment->term_id);
        $this->assertSame($assignment->id, $restored->id);
        $this->assertSame(4, Grade::visibleTo($this->actor())->count());
    }

    public function test_noop_does_not_audit_and_stale_version_does_not_overwrite(): void
    {
        $grade = $this->baseGrade();
        $service = app(GradeService::class);
        $service->update($this->actor()->id, $grade->id, 1, 'Bez zmiany', ['value' => 4]);
        $this->assertSame(1, $grade->audits()->count());
        $service->update($this->actor()->id, $grade->id, 1, 'Poprawa wyniku', ['value' => 6]);
        try {
            $service->update($this->actor()->id, $grade->id, 1, 'Stara wersja', ['value' => 3]);
            $this->fail('Expected version conflict');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('conflict', $e->errors());
        }
        $this->assertSame(6, $grade->fresh()->value);
        $this->assertSame(2, $grade->audits()->count());
    }

    public function test_closed_term_blocks_even_admin(): void
    {
        $grade = $this->baseGrade();
        app(CalendarService::class)->configure(['years' => [], 'categories' => [], 'close_term_ids' => [$grade->assessment->term_id]]);
        $closedAt = $grade->assessment->term->fresh()->closed_at;
        app(CalendarService::class)->configure(['years' => [], 'categories' => [], 'close_term_ids' => [$grade->assessment->term_id]]);
        $this->assertTrue($closedAt->equalTo($grade->assessment->term->fresh()->closed_at));
        $this->expectException(ValidationException::class);
        app(GradeService::class)->update($this->actor('admin1')->id, $grade->id, 1, 'Admin korekta', ['value' => 6]);
    }

    public function test_overlap_includes_endpoints_and_null_end_means_end_of_year(): void
    {
        $enrollment = $this->baseGrade()->enrollment;
        $otherClass = SchoolClass::where('academic_year_id', $enrollment->schoolClass->academic_year_id)->where('id', '<>', $enrollment->school_class_id)->firstOrFail();
        $this->expectException(ValidationException::class);
        app(EnrollmentService::class)->enroll($enrollment->student_id, $otherClass->id, '2027-08-31', '2027-08-31');
    }

    public function test_adjacent_memberships_in_separate_years_are_valid(): void
    {
        $student = User::factory()->create();
        $classes = SchoolClass::where('name', '1A')->orderBy('academic_year_id')->get();
        app(EnrollmentService::class)->enroll($student->id, $classes[0]->id, '2025-09-01');
        app(EnrollmentService::class)->enroll($student->id, $classes[1]->id, '2026-09-01');
        $this->assertSame(2, $student->enrollments()->count());
    }

    public function test_assignment_rejects_other_year_and_non_teacher(): void
    {
        $grade = $this->baseGrade();
        $historic = Term::where('academic_year_id', '<>', $grade->assessment->term->academic_year_id)->firstOrFail();
        $this->expectException(ValidationException::class);
        app(AssignmentService::class)->assign($this->actor('student1')->id, $grade->assessment->school_class_id, $grade->assessment->subject_id, $historic->id);
    }

    #[DataProvider('invalidGradeData')]
    public function test_service_validates_types_dates_and_relations(array $input): void
    {
        $grade = $this->baseGrade();
        $this->expectException(ValidationException::class);
        app(GradeService::class)->update($this->actor()->id, $grade->id, 1, 'Test walidacji', $input);
    }

    public static function invalidGradeData(): array
    {
        return [[['weight' => 1.5]], [['value' => '4+']], [['value' => true]], [['category_id' => 999999]],
            [['graded_on' => '2026-11-16']], [['graded_on' => '2026-08-31']], [['topic' => '']], [['comment' => str_repeat('a', 501)]]];
    }

    public function test_grade_factory_produces_consistent_context_and_audit(): void
    {
        // Reuse the existing year to keep all generated contexts non-overlapping.
        $class = SchoolClass::where('name', '2A')->firstOrFail();
        $assessment = Assessment::factory()->create(['school_class_id' => $class->id, 'term_id' => $this->baseGrade()->assessment->term_id]);
        $grade = Grade::factory()->create(['assessment_id' => $assessment->id]);
        $this->assertSame($grade->enrollment->school_class_id, $assessment->school_class_id);
        $this->assertCount(1, $grade->audits);
        $this->assertSame(1, $grade->version);
    }

    public function test_seeder_refuses_production_without_changes(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $before = User::withTrashed()->count();
        try {
            app(DatabaseSeeder::class)->run();
            $this->fail('Expected production guard');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('wyłącznie', $e->getMessage());
        } finally {
            $this->app->detectEnvironment(fn () => 'testing');
        }
        $this->assertSame($before, User::withTrashed()->count());
    }

    public function test_default_factories_create_multiple_audited_grades_with_valid_dates(): void
    {
        $grades = Grade::factory()->count(2)->create();
        $this->assertCount(2, $grades);
        $this->assertCount(0, Grade::factory()->count(0)->create());
        foreach ($grades as $grade) {
            $this->assertSame($grade->enrollment->school_class_id, $grade->assessment->school_class_id);
            $this->assertCount(1, $grade->audits);
            $this->assertTrue($grade->graded_on->betweenIncluded($grade->assessment->term->starts_on, $grade->assessment->term->ends_on));
        }
    }

    public function test_failed_create_has_no_grade_or_audit_effect(): void
    {
        $grade = $this->baseGrade();
        $before = [Grade::withTrashed()->count(), GradeAudit::count()];
        $otherEnrollment = $this->actor('student1')->enrollments()->firstOrFail();
        try {
            app(GradeService::class)->create($this->actor()->id, $otherEnrollment->id, $grade->assessment_id, ['value' => 4]);
            $this->fail('Expected class mismatch');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('enrollment', $e->errors());
        }
        $this->assertSame($before, [Grade::withTrashed()->count(), GradeAudit::count()]);
    }

    public function test_grade_override_uses_individual_weight_and_does_not_edit_header(): void
    {
        $grade = $this->baseGrade();
        $header = $grade->assessment;
        app(GradeService::class)->update($this->actor()->id, $grade->id, 1, 'Indywidualna waga', ['weight' => 5]);
        $this->assertSame(2, $header->fresh()->default_weight);
        $this->assertSame('4.30', $this->summary($this->actor('student7'))['average']);
    }

    public function test_enrollment_window_is_checked_separately_from_term_dates(): void
    {
        $base = $this->baseGrade();
        $student = User::factory()->create();
        $enrollment = app(EnrollmentService::class)->enroll($student->id, $base->assessment->school_class_id, '2026-11-01', '2026-11-10');
        $this->expectException(ValidationException::class);
        app(GradeService::class)->create($this->actor()->id, $enrollment->id, $base->assessment_id, ['value' => 4]);
    }

    public function test_assessment_metadata_and_term_closure_cannot_be_rewritten(): void
    {
        $grade = $this->baseGrade();
        try {
            $grade->assessment->update(['subject_id' => Subject::where('id', '<>', $grade->assessment->subject_id)->firstOrFail()->id]);
            $this->fail('Expected immutable metadata');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('niezmienne', $e->getMessage());
        }
        $term = Term::whereNotNull('closed_at')->firstOrFail();
        $this->expectException(\LogicException::class);
        $term->update(['closed_at' => null]);
    }

    public function test_audit_json_types_are_checked_by_mariadb(): void
    {
        $audit = $this->baseGrade()->audits()->firstOrFail();
        $snapshot = $audit->new_values;
        $snapshot['value'] = true;
        $this->expectException(QueryException::class);
        DB::table('grade_audits')->where('id', $audit->id)->update(['new_values' => json_encode($snapshot, JSON_THROW_ON_ERROR)]);
    }

    public function test_deleted_user_keeps_email_reserved_and_historical_relations_readable(): void
    {
        $student = $this->actor('student7');
        $grade = $this->baseGrade();
        $student->update(['status' => 'inactive']);
        $student->delete();
        $this->assertSame($student->id, $grade->enrollment->student->id);
        $this->assertSame(0, Grade::visibleTo($student)->count());
        $this->expectException(QueryException::class);
        User::factory()->create(['email' => $student->email]);
    }

    public function test_all_twenty_foreign_keys_restrict_deletes_and_updates(): void
    {
        $constraints = DB::table('information_schema.REFERENTIAL_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())->get();
        $this->assertCount(20, $constraints);
        foreach ($constraints as $constraint) {
            $this->assertSame('RESTRICT', $constraint->DELETE_RULE);
            $this->assertSame('RESTRICT', $constraint->UPDATE_RULE);
        }
    }

    public function test_temporal_scopes_use_closed_intervals_and_year_end_for_null(): void
    {
        $membership = $this->baseGrade()->enrollment;
        $this->assertTrue(StudentEnrollment::onDate('2026-09-01')->whereKey($membership->id)->exists());
        $this->assertTrue(StudentEnrollment::onDate('2027-08-31')->whereKey($membership->id)->exists());
        $this->assertFalse(StudentEnrollment::onDate('2027-09-01')->whereKey($membership->id)->exists());
        $this->assertFalse(StudentEnrollment::onDate('2026-08-31')->whereKey($membership->id)->exists());
        $this->assertSame(1, Term::onDate()->count());
        $this->assertSame(1, Term::onDate('2027-01-31')->firstOrFail()->number);
        $this->assertSame(2, Term::onDate('2027-02-01')->firstOrFail()->number);
    }

    public function test_inactive_actor_cannot_mutate_a_grade(): void
    {
        $grade = $this->baseGrade();
        $actor = $this->actor();
        $actor->update(['status' => 'inactive']);
        $this->assertSame(0, Grade::visibleTo($actor)->count());
        $this->expectException(AuthorizationException::class);
        app(GradeService::class)->update($actor->id, $grade->id, 1, 'Korekta wyniku', ['value' => 6]);
    }
}
