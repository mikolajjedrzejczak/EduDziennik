<?php

use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\StudentEnrollment;
use App\Models\Subject;
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
Artisan::call('migrate:fresh', ['--force' => true]);
$admin = User::factory()->admin()->create(['email' => 'admin-dashboard@example.test', 'password' => 'Playwright-dashboard-1']);
if (($argv[1] ?? '') === 'empty') {
    exit(0);
}
$year = AcademicYear::create(['name' => '2026/2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-08-31']);
$term = Term::create(['academic_year_id' => $year->id, 'number' => 1, 'starts_on' => '2026-09-01', 'ends_on' => '2027-01-31']);
Term::create(['academic_year_id' => $year->id, 'number' => 2, 'starts_on' => '2027-02-01', 'ends_on' => '2027-06-30']);
$class = SchoolClass::create(['academic_year_id' => $year->id, 'name' => '2A']);
$subject = Subject::create(['name' => 'Matematyka']);
$student = User::factory()->state(['role' => 'student'])->create(['first_name' => 'Jan', 'last_name' => 'Kowalski']);
$enrollment = StudentEnrollment::factory()->create(['school_class_id' => $class->id, 'student_id' => $student->id]);
User::factory()->teacher()->create(['status' => 'inactive']);
foreach (range(1, 7) as $i) {
    $assessment = Assessment::factory()->create(['school_class_id' => $class->id, 'subject_id' => $subject->id,
        'term_id' => $term->id, 'created_by' => $admin->id, 'graded_on' => '2026-10-10']);
    $grade = Grade::factory()->create(['assessment_id' => $assessment->id, 'student_enrollment_id' => $enrollment->id]);

}
