<?php

namespace Tests\Feature\Admin;

use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Services\Users\UserService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ConcurrentUserCreationTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function revocations(): iterable
    {
        foreach (['role', 'inactive', 'deleted'] as $revocation) {
            foreach (['admin', 'student', 'teacher'] as $role) {
                yield "$revocation / $role" => [$revocation, $role];
            }
        }
    }

    #[DataProvider('revocations')]
    public function test_revocation_committed_after_request_authorization_prevents_account_and_relations(string $revocation, string $role): void
    {
        // Committed fixtures: the worker must see the actor before revocation.
        Artisan::call('migrate:fresh', ['--force' => true]);
        $operator = User::factory()->admin()->create();
        $actor = User::factory()->admin()->create();
        $class = SchoolClass::factory()->create();
        $subject = Subject::factory()->create();
        $term = Term::factory()->create(['academic_year_id' => $class->academic_year_id]);
        $data = [
            'account' => ['first_name' => 'Anna', 'last_name' => 'Nowak', 'email' => 'race@example.test', 'password' => 'only-in-test-fixture', 'role' => $role, 'status' => 'active'],
            'enrollment' => $role === 'student' ? ['school_class_id' => $class->id, 'starts_on' => $class->academicYear->starts_on->toDateString()] : null,
            'assignments' => $role === 'teacher' ? [['school_class_id' => $class->id, 'subject_id' => $subject->id, 'term_id' => $term->id]] : [],
        ];
        $input = new InputStream;
        $worker = new Process([PHP_BINARY, base_path('tests/Infrastructure/concurrent-user-create.php'), (string) $actor->id, json_encode($data, JSON_THROW_ON_ERROR)], base_path(), [
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mariadb', 'DB_DATABASE' => 'edudziennik_test', 'DB_URL' => '',
        ], $input);
        $worker->setTimeout(15);
        DB::beginTransaction();
        try {
            DB::table('domain_locks')->where('key', 'admin_membership')->lockForUpdate()->firstOrFail();
            $worker->start();
            $this->assertTrue($worker->waitUntil(fn ($type, $output) => str_contains($output, 'authorized-and-contended')), $worker->getErrorOutput());
            if ($revocation === 'deleted') {
                app(UserService::class)->delete($operator->id, $actor->id, $actor->last_name);
            } else {
                app(UserService::class)->update($operator->id, $actor->id, [
                    ...$actor->only(['first_name', 'last_name', 'email', 'role', 'status']),
                    ...($revocation === 'role' ? ['role' => 'teacher'] : ['status' => 'inactive']),
                ]);
            }
            DB::commit();
            $input->write("committed\n");
            $input->close();
            $this->assertSame(0, $worker->wait(), $worker->getErrorOutput().$worker->getOutput());
            $this->assertStringContainsString('rejected', $worker->getOutput());
            $this->assertDatabaseMissing('users', ['email' => 'race@example.test']);
            $this->assertDatabaseCount('student_enrollments', 0);
            $this->assertDatabaseCount('teacher_assignments', 0);
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            $worker->stop();
            Artisan::call('migrate:fresh', ['--force' => true]);
        }
    }
}
