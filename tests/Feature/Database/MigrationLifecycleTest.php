<?php

namespace Tests\Feature\Database;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrationLifecycleTest extends TestCase
{
    public function test_empty_test_database_can_migrate_rollback_and_migrate_again(): void
    {
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
        foreach (['users', 'domain_locks', 'academic_years', 'terms', 'school_classes', 'subjects', 'student_enrollments', 'teacher_assignments', 'grade_categories', 'assessments', 'grades', 'grade_audits', 'sessions', 'cache', 'cache_locks'] as $table) {
            $this->assertTrue(Schema::hasTable($table), $table);
        }
        foreach (['jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens'] as $table) {
            $this->assertFalse(Schema::hasTable($table), $table);
        }
        $this->artisan('migrate:rollback', ['--force' => true])->assertExitCode(0);
        $this->assertFalse(Schema::hasTable('users'));
        $this->assertFalse(Schema::hasTable('grade_audits'));
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
    }
}
