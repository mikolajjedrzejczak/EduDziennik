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
use Tests\Support\TestDatabase;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
TestDatabase::assertSafe($app->environment(), config('database.default'), DB::connection()->getConfig());
if (($argv[1] ?? '') === 'revoke') {
    TeacherAssignment::whereHas('subject', fn ($q) => $q->where('name', 'Matematyka'))->update(['revoked_at' => now()]);
    exit(0);
}
Artisan::call('migrate:fresh', ['--force' => true]);
$year = AcademicYear::create(['name' => '2026/2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-08-31']);
$term = Term::create(['academic_year_id' => $year->id, 'number' => 1, 'starts_on' => '2026-09-01', 'ends_on' => '2027-01-31']);
$archived = Term::create(['academic_year_id' => $year->id, 'number' => 2, 'starts_on' => '2027-02-01', 'ends_on' => '2027-06-30', 'closed_at' => now()]);
$class = SchoolClass::create(['academic_year_id' => $year->id, 'name' => '2A']);
$teacher = User::factory()->teacher()->create(['email' => 'teacher-classes@example.test', 'password' => 'Playwright-classes-1']);
foreach (['Matematyka', 'Fizyka'] as $name) {
    $subject = Subject::create(['name' => $name]);
    TeacherAssignment::create(['teacher_id' => $teacher->id, 'school_class_id' => $class->id, 'subject_id' => $subject->id, 'term_id' => $term->id]);
    TeacherAssignment::create(['teacher_id' => $teacher->id, 'school_class_id' => $class->id, 'subject_id' => $subject->id, 'term_id' => $archived->id]);
}
echo "Seeded F-202 test fixtures.\n";
