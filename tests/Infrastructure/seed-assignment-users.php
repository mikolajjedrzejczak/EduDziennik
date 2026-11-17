<?php

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Models\Term;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Support\TestDatabase;

// F-308 Playwright fixture: isolated edudziennik_test database only.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
TestDatabase::assertSafe($app->environment(), config('database.default'), DB::connection()->getConfig());
Artisan::call('migrate:fresh', ['--force' => true]);
$year = AcademicYear::create(['name' => '2026/2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-08-31']);
Term::create(['academic_year_id' => $year->id, 'number' => 1, 'starts_on' => '2026-09-01', 'ends_on' => '2027-01-31']);
SchoolClass::create(['academic_year_id' => $year->id, 'name' => '2A']);
Subject::create(['name' => 'Matematyka']);
$password = Hash::make('Playwright-assign-1');
User::create(['first_name' => 'Marta', 'last_name' => 'Testowa', 'email' => 'assign-admin@example.test', 'password' => $password, 'role' => 'admin', 'status' => 'active']);
User::create(['first_name' => 'Anna', 'last_name' => 'Nowak', 'email' => 'assign-teacher@example.test', 'password' => $password, 'role' => 'teacher', 'status' => 'active']);
// Historical options remain readable after their write context is retired.
$pastYear = AcademicYear::create(['name' => '2025/2026', 'starts_on' => '2025-09-01', 'ends_on' => '2026-08-31']);
$pastTerm = Term::create(['academic_year_id' => $pastYear->id, 'number' => 1, 'starts_on' => '2025-09-01', 'ends_on' => '2026-01-31', 'closed_at' => now()]);
$pastClass = SchoolClass::create(['academic_year_id' => $pastYear->id, 'name' => '1B']);
$pastSubject = Subject::create(['name' => 'Geografia']);
foreach (['inactive', 'deleted'] as $state) {
    $teacher = User::create(['first_name' => 'Jan', 'last_name' => $state === 'deleted' ? 'Usunięty' : 'Nieaktywny', 'email' => 'assign-'.$state.'@example.test', 'password' => $password, 'role' => 'teacher', 'status' => 'inactive']);
    TeacherAssignment::create(['teacher_id' => $teacher->id, 'school_class_id' => $pastClass->id, 'subject_id' => $pastSubject->id, 'term_id' => $pastTerm->id, 'revoked_at' => now()]);
    if ($state === 'deleted') {
        $teacher->delete();
    }
}
$pastClass->delete();
$pastSubject->delete();
echo "Seeded assignment fixtures.\n";
