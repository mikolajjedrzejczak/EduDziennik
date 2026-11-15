<?php

namespace Tests\Feature\Database;

use App\Models\Assessment;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\StudentEnrollment;
use App\Models\Term;
use App\Models\User;
use App\Services\School\AssignmentService;
use App\Services\School\CalendarService;
use App\Services\School\EnrollmentService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ConcurrentWriteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Committed fixtures are required: the worker has its own connection/process.
        Artisan::call('migrate:fresh', ['--force' => true]);
        config(['demo.password' => 'only-in-test-fixture', 'demo.date' => '2026-11-15']);
        $this->seed();
    }

    protected function tearDown(): void
    {
        Artisan::call('migrate:fresh', ['--force' => true]);
        parent::tearDown();
    }

    private function worker(array $arguments): Process
    {
        $process = new Process([PHP_BINARY, base_path('tests/Infrastructure/concurrent-write.php'), ...array_map(strval(...), $arguments)], base_path(), [
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mariadb', 'DB_DATABASE' => 'edudziennik_test', 'DB_URL' => '',
        ]);
        $process->setTimeout(15);
        $process->start();
        $this->assertTrue($process->waitUntil(fn ($type, $output) => str_contains($output, 'contended')));

        return $process;
    }

    public function test_concurrent_enrollment_is_rejected_after_first_membership_commits(): void
    {
        $student = User::factory()->create();
        $classes = SchoolClass::whereHas('academicYear', fn ($q) => $q->where('name', '2026/2027'))->get();
        DB::beginTransaction();
        $worker = null;
        try {
            User::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $worker = $this->worker(['enroll', $student->id, $classes[1]->id]);
            app(EnrollmentService::class)->enroll($student->id, $classes[0]->id, '2026-09-01');
            DB::commit();
            $this->assertSame(0, $worker->wait(), $worker->getErrorOutput());
            $this->assertStringContainsString('rejected', $worker->getOutput());
            $this->assertSame(1, StudentEnrollment::where('student_id', $student->id)->count());
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            $worker?->stop();
        }
    }

    public function test_two_concurrent_transfers_cannot_split_the_same_membership_twice(): void
    {
        $source = User::where('email', 'student1@example.test')->firstOrFail()->enrollments()->where('starts_on', '2026-09-01')->firstOrFail();
        $targets = SchoolClass::factory()->count(2)->create(['academic_year_id' => $source->schoolClass->academic_year_id]);
        DB::beginTransaction();
        $worker = null;
        try {
            User::whereKey($source->student_id)->lockForUpdate()->firstOrFail();
            $worker = $this->worker(['transfer', $source->student_id, $targets[1]->id, $source->id]);
            app(EnrollmentService::class)->enroll($source->student_id, $targets[0]->id, '2026-11-15', null, $source->id);
            DB::commit();
            $this->assertSame(0, $worker->wait(), $worker->getErrorOutput());
            $this->assertStringContainsString('rejected', $worker->getOutput());
            $this->assertSame('2026-11-14', $source->fresh()->ends_on->toDateString());
            $this->assertSame(1, StudentEnrollment::where('student_id', $source->student_id)->onDate('2026-11-15')->count());
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            $worker?->stop();
        }
    }

    public function test_grade_waiting_for_closed_term_cannot_commit(): void
    {
        $header = Assessment::where('topic', 'Fixture 1')->firstOrFail();
        $teacher = User::where('email', 'teacher1@example.test')->firstOrFail();
        $enrollment = User::where('email', 'student9@example.test')->firstOrFail()->enrollments()->firstOrFail();
        $before = Grade::withTrashed()->count();
        DB::beginTransaction();
        $worker = null;
        try {
            Term::whereKey($header->term_id)->lockForUpdate()->firstOrFail();
            $worker = $this->worker(['grade', $teacher->id, $enrollment->id, $header->id]);
            app(CalendarService::class)->configure(['years' => [], 'categories' => [], 'close_term_ids' => [$header->term_id]]);
            DB::commit();
            $this->assertSame(0, $worker->wait(), $worker->getErrorOutput());
            $this->assertStringContainsString('rejected', $worker->getOutput());
            $this->assertSame($before, Grade::withTrashed()->count());
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            $worker?->stop();
        }
    }

    public function test_grade_waiting_for_a_revoke_is_rejected_and_co_teacher_scope_survives(): void
    {
        $header = Assessment::where('topic', 'Fixture 1')->firstOrFail();
        $teacher = User::where('email', 'teacher1@example.test')->firstOrFail();
        $enrollment = User::where('email', 'student9@example.test')->firstOrFail()->enrollments()->firstOrFail();
        $assignment = $teacher->assignments()->where(['school_class_id' => $header->school_class_id, 'subject_id' => $header->subject_id, 'term_id' => $header->term_id])->firstOrFail();
        $coTeacher = User::where('email', 'teacher2@example.test')->firstOrFail();
        $kept = app(AssignmentService::class)->assign($coTeacher->id, $assignment->school_class_id, $assignment->subject_id, $assignment->term_id);
        $before = Grade::withTrashed()->count();
        DB::beginTransaction();
        $worker = null;
        try {
            Term::whereKey($header->term_id)->lockForUpdate()->firstOrFail();
            $worker = $this->worker(['grade', $teacher->id, $enrollment->id, $header->id]);
            app(AssignmentService::class)->revoke($assignment->id);
            DB::commit();
            $this->assertSame(0, $worker->wait(), $worker->getErrorOutput());
            $this->assertStringContainsString('rejected', $worker->getOutput());
            $this->assertSame($before, Grade::withTrashed()->count());
            $this->assertNotNull($assignment->fresh()->revoked_at);
            $this->assertNull($kept->fresh()->revoked_at);
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            $worker?->stop();
        }
    }
}
