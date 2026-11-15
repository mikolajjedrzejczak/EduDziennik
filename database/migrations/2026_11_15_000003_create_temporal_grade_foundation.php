<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MariaDB DDL commits implicitly: reject unsafe starter data before any schema change.
        foreach (['jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens'] as $table) {
            if (DB::table($table)->exists()) {
                throw new RuntimeException('Nieużywana tabela startera zawiera dane; wymagana ręczna migracja.');
            }
        }
        if (DB::table('users')->whereRaw("CHAR_LENGTH(SUBSTRING_INDEX(name, ' ', 1)) > 80 OR CHAR_LENGTH(TRIM(SUBSTRING(name, LOCATE(' ', CONCAT(name, ' ')) + 1))) > 80 OR CHAR_LENGTH(email) > 254")->exists()) {
            throw new RuntimeException('Dane startera przekraczają długości docelowego schematu.');
        }
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name', 80)->default('');
            $table->string('last_name', 80)->default('');
            $table->string('role', 16)->default('student');
            $table->string('status', 16)->default('active');
            $table->dateTime('last_login_at', 6)->nullable();
            $table->softDeletes('deleted_at', 6);
            $table->index(['role', 'status', 'deleted_at'], 'users_role_status_idx');
            $table->index(['last_name', 'first_name', 'id'], 'users_name_idx');
        });
        DB::statement("UPDATE users SET first_name = SUBSTRING_INDEX(name, ' ', 1), last_name = LEFT(TRIM(SUBSTRING(name, LOCATE(' ', CONCAT(name, ' ')) + 1)), 80), email = LOWER(email)");
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('name');
            $table->string('email', 254)->change();
            $table->dateTime('created_at', 6)->nullable()->change();
            $table->dateTime('updated_at', 6)->nullable()->change();
        });
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('student','teacher','admin')), ADD CONSTRAINT users_status_check CHECK (status IN ('active','inactive')), ADD CONSTRAINT users_email_check CHECK (BINARY email = BINARY LOWER(email))");
        Schema::table('sessions', function (Blueprint $table) {
            $table->foreign('user_id', 'sessions_user_fk')->references('id')->on('users')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::create('domain_locks', function (Blueprint $table) {
            $table->string('key', 64)->primary();
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
        });
        DB::table('domain_locks')->insert([
            ['key' => 'admin_membership', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'calendar_configuration', 'created_at' => now(), 'updated_at' => now()],
        ]);
        Schema::create('academic_years', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('name', 9)->unique();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
        });
        DB::statement('ALTER TABLE academic_years ADD CONSTRAINT academic_years_check_0 CHECK (starts_on <= ends_on)');
        Schema::create('terms', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('academic_year_id');
            $table->foreign('academic_year_id', 'terms_academic_year_id_fk')->references('id')->on('academic_years')->restrictOnDelete()->restrictOnUpdate();
            $table->unsignedTinyInteger('number');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->dateTime('closed_at', 6)->nullable();
            $table->unique(['academic_year_id', 'number'], 'terms_year_number_uq');
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
        });
        DB::statement('ALTER TABLE terms ADD CONSTRAINT terms_check_0 CHECK (number IN (1, 2))');
        DB::statement('ALTER TABLE terms ADD CONSTRAINT terms_check_1 CHECK (starts_on <= ends_on)');
        Schema::create('school_classes', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('academic_year_id');
            $table->foreign('academic_year_id', 'school_classes_academic_year_id_fk')->references('id')->on('academic_years')->restrictOnDelete()->restrictOnUpdate();
            $table->string('name', 20);
            $table->softDeletes('deleted_at', 6);
            $table->unique(['academic_year_id', 'name'], 'classes_year_name_uq');
            $table->index(['academic_year_id', 'deleted_at'], 'classes_year_deleted_idx');
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
        });
        Schema::create('subjects', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('name', 100)->unique();
            $table->softDeletes('deleted_at', 6);
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
        });
        Schema::create('student_enrollments', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('student_id');
            $table->foreign('student_id', 'enr_student_id_fk')->references('id')->on('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('school_class_id');
            $table->foreign('school_class_id', 'enr_school_class_id_fk')->references('id')->on('school_classes')->restrictOnDelete()->restrictOnUpdate();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->unique(['student_id', 'starts_on'], 'enrollments_student_start_uq');
            $table->index(['school_class_id', 'starts_on', 'ends_on'], 'enrollments_class_dates_idx');
            $table->index(['student_id', 'starts_on', 'ends_on'], 'enrollments_student_dates_idx');
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
        });
        DB::statement('ALTER TABLE student_enrollments ADD CONSTRAINT student_enrollments_check_0 CHECK (ends_on IS NULL OR ends_on >= starts_on)');
        Schema::create('teacher_assignments', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('teacher_id');
            $table->foreign('teacher_id', 'asg_teacher_id_fk')->references('id')->on('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('school_class_id');
            $table->foreign('school_class_id', 'asg_school_class_id_fk')->references('id')->on('school_classes')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('subject_id');
            $table->foreign('subject_id', 'asg_subject_id_fk')->references('id')->on('subjects')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('term_id');
            $table->foreign('term_id', 'asg_term_id_fk')->references('id')->on('terms')->restrictOnDelete()->restrictOnUpdate();
            $table->dateTime('revoked_at', 6)->nullable();
            $table->unique(['teacher_id', 'school_class_id', 'subject_id', 'term_id'], 'assignments_context_uq');
            $table->index(['teacher_id', 'revoked_at', 'term_id'], 'assignments_teacher_idx');
            $table->index(['school_class_id', 'subject_id', 'term_id', 'revoked_at'], 'assignments_context_idx');
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
        });
        Schema::create('grade_categories', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('name', 60)->unique();
            $table->unsignedTinyInteger('default_weight');
            $table->boolean('is_active')->default(true);
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
        });
        DB::statement('ALTER TABLE grade_categories ADD CONSTRAINT grade_categories_check_0 CHECK (default_weight BETWEEN 1 AND 5)');
        Schema::create('assessments', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('school_class_id');
            $table->foreign('school_class_id', 'assess_school_class_id_fk')->references('id')->on('school_classes')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('subject_id');
            $table->foreign('subject_id', 'assess_subject_id_fk')->references('id')->on('subjects')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('term_id');
            $table->foreign('term_id', 'assess_term_id_fk')->references('id')->on('terms')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('category_id');
            $table->foreign('category_id', 'assess_category_id_fk')->references('id')->on('grade_categories')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('created_by');
            $table->foreign('created_by', 'assess_created_by_fk')->references('id')->on('users')->restrictOnDelete()->restrictOnUpdate();
            $table->string('topic', 120);
            $table->unsignedTinyInteger('default_weight');
            $table->date('graded_on');
            $table->softDeletes('deleted_at', 6);
            $table->index(['school_class_id', 'subject_id', 'term_id', 'graded_on', 'id'], 'assessments_context_idx');
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
        });
        DB::statement('ALTER TABLE assessments ADD CONSTRAINT assessments_check_0 CHECK (default_weight BETWEEN 1 AND 5)');
        Schema::create('grades', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('student_enrollment_id');
            $table->foreign('student_enrollment_id', 'grades_student_enrollment_id_fk')->references('id')->on('student_enrollments')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('assessment_id');
            $table->foreign('assessment_id', 'grades_assessment_id_fk')->references('id')->on('assessments')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('category_id');
            $table->foreign('category_id', 'grades_category_id_fk')->references('id')->on('grade_categories')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('issued_by');
            $table->foreign('issued_by', 'grades_issued_by_fk')->references('id')->on('users')->restrictOnDelete()->restrictOnUpdate();
            $table->unsignedTinyInteger('value');
            $table->unsignedTinyInteger('weight');
            $table->string('topic', 120);
            $table->string('comment', 500)->nullable();
            $table->date('graded_on');
            $table->unsignedInteger('version')->default(1);
            $table->softDeletes('deleted_at', 6);
            $table->unique(['student_enrollment_id', 'assessment_id'], 'grades_cell_uq');
            $table->index(['assessment_id', 'deleted_at', 'graded_on', 'id'], 'grades_assessment_idx');
            $table->index(['student_enrollment_id', 'deleted_at', 'graded_on', 'id'], 'grades_enrollment_idx');
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
        });
        DB::statement('ALTER TABLE grades ADD CONSTRAINT grades_check_0 CHECK (value BETWEEN 1 AND 6)');
        DB::statement('ALTER TABLE grades ADD CONSTRAINT grades_check_1 CHECK (weight BETWEEN 1 AND 5)');
        DB::statement('ALTER TABLE grades ADD CONSTRAINT grades_check_2 CHECK (version >= 1)');
        Schema::create('grade_audits', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('grade_id');
            $table->foreign('grade_id', 'audit_grade_id_fk')->references('id')->on('grades')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('actor_id');
            $table->foreign('actor_id', 'audit_actor_id_fk')->references('id')->on('users')->restrictOnDelete()->restrictOnUpdate();
            $table->string('action', 8);
            $table->dateTime('occurred_at', 6);
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('reason', 500)->nullable();
            $table->unsignedInteger('grade_version');
            $table->unique(['grade_id', 'grade_version'], 'audits_grade_version_uq');
            $table->index(['grade_id', 'occurred_at', 'id'], 'audits_grade_time_idx');
            $table->index(['occurred_at', 'id'], 'audits_time_idx');
        });
        DB::statement("ALTER TABLE grade_audits ADD CONSTRAINT grade_audits_check_0 CHECK (action IN ('create','update','delete'))");
        DB::statement('ALTER TABLE grade_audits ADD CONSTRAINT grade_audits_check_1 CHECK (grade_version >= 1)');
        DB::statement("ALTER TABLE grade_audits ADD CONSTRAINT grade_audits_check_2 CHECK ((action = 'create' AND old_values IS NULL AND new_values IS NOT NULL AND reason IS NULL) OR (action IN ('update','delete') AND old_values IS NOT NULL AND new_values IS NOT NULL AND reason IS NOT NULL AND CHAR_LENGTH(TRIM(reason)) BETWEEN 3 AND 500))");
        DB::statement("ALTER TABLE grade_audits ADD CONSTRAINT grade_audits_check_3 CHECK (action <> 'delete' OR (JSON_CONTAINS_PATH(new_values, 'one', '$.deleted_at') = 1 AND JSON_TYPE(JSON_EXTRACT(new_values, '$.deleted_at')) = 'STRING'))");
        DB::statement("ALTER TABLE grade_audits ADD CONSTRAINT audits_new_values_schema CHECK (new_values IS NULL OR (JSON_CONTAINS_PATH(new_values, 'all', '$.schema_version', '$.id', '$.student_enrollment_id', '$.student_id', '$.assessment_id', '$.school_class_id', '$.subject_id', '$.term_id', '$.category_id', '$.value', '$.weight', '$.topic', '$.comment', '$.graded_on', '$.issued_by', '$.version', '$.deleted_at') = 1 AND JSON_EXTRACT(new_values, '$.schema_version') = 1))");
        DB::statement("ALTER TABLE grade_audits ADD CONSTRAINT audits_old_values_schema CHECK (old_values IS NULL OR (JSON_CONTAINS_PATH(old_values, 'all', '$.schema_version', '$.id', '$.student_enrollment_id', '$.student_id', '$.assessment_id', '$.school_class_id', '$.subject_id', '$.term_id', '$.category_id', '$.value', '$.weight', '$.topic', '$.comment', '$.graded_on', '$.issued_by', '$.version', '$.deleted_at') = 1 AND JSON_EXTRACT(old_values, '$.schema_version') = 1))");
        foreach (['old_values', 'new_values'] as $column) {
            $checks = ["JSON_LENGTH($column) = 17", "JSON_TYPE(JSON_EXTRACT($column, '$.schema_version')) = 'INTEGER'", "JSON_EXTRACT($column, '$.schema_version') = 1"];
            foreach (['id', 'student_enrollment_id', 'student_id', 'assessment_id', 'school_class_id', 'subject_id', 'term_id', 'category_id', 'issued_by'] as $field) {
                $checks[] = "JSON_TYPE(JSON_EXTRACT($column, '$.$field')) = 'STRING'";
                $checks[] = "JSON_UNQUOTE(JSON_EXTRACT($column, '$.$field')) REGEXP '^[1-9][0-9]*$'";
            }
            foreach (['value', 'weight', 'version'] as $field) {
                $checks[] = "JSON_TYPE(JSON_EXTRACT($column, '$.$field')) = 'INTEGER'";
            }
            $checks[] = "JSON_EXTRACT($column, '$.value') BETWEEN 1 AND 6";
            $checks[] = "JSON_EXTRACT($column, '$.weight') BETWEEN 1 AND 5";
            $checks[] = "JSON_EXTRACT($column, '$.version') >= 1";
            $checks[] = "JSON_TYPE(JSON_EXTRACT($column, '$.topic')) = 'STRING'";
            $checks[] = "JSON_TYPE(JSON_EXTRACT($column, '$.graded_on')) = 'STRING'";
            $checks[] = "JSON_TYPE(JSON_EXTRACT($column, '$.comment')) IN ('STRING', 'NULL')";
            $checks[] = "JSON_TYPE(JSON_EXTRACT($column, '$.deleted_at')) IN ('STRING', 'NULL')";
            DB::statement("ALTER TABLE grade_audits ADD CONSTRAINT audits_{$column}_types CHECK ($column IS NULL OR (".implode(' AND ', $checks).'))');
        }
        Schema::drop('jobs');
        Schema::drop('job_batches');
        Schema::drop('failed_jobs');
        Schema::drop('password_reset_tokens');
    }

    public function down(): void
    {
        foreach (['users', 'academic_years', 'subjects', 'grade_categories', 'grades', 'grade_audits'] as $table) {
            if (DB::table($table)->exists()) {
                throw new RuntimeException('Rollback fundamentu jest dozwolony tylko na pustej bazie testowej.');
            }
        }
        Schema::dropIfExists('grade_audits');
        Schema::dropIfExists('grades');
        Schema::dropIfExists('assessments');
        Schema::dropIfExists('grade_categories');
        Schema::dropIfExists('teacher_assignments');
        Schema::dropIfExists('student_enrollments');
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('school_classes');
        Schema::dropIfExists('terms');
        Schema::dropIfExists('academic_years');
        Schema::dropIfExists('domain_locks');
        Schema::table('sessions', fn (Blueprint $table) => $table->dropForeign('sessions_user_fk'));
        DB::statement('ALTER TABLE users DROP CONSTRAINT users_role_check, DROP CONSTRAINT users_status_check, DROP CONSTRAINT users_email_check');
        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->default('');
            $table->dropIndex('users_role_status_idx');
            $table->dropIndex('users_name_idx');
            $table->dropColumn(['first_name', 'last_name', 'role', 'status', 'last_login_at', 'deleted_at']);
        });
        // Restore the starter tables using their original migrations, without recreating users/sessions.
        (require database_path('migrations/0001_01_01_000002_create_jobs_table.php'))->up();
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }
};
