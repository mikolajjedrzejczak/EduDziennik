<?php

use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\Grade;
use App\Models\GradeCategory;
use App\Models\SchoolClass;
use App\Models\StudentEnrollment;
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
Artisan::call('migrate:fresh', ['--force' => true]);
$year = AcademicYear::create(['name' => '2026/2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-08-31']);
$term = Term::create(['academic_year_id' => $year->id, 'number' => 1, 'starts_on' => '2026-09-01', 'ends_on' => '2027-01-31']);
$class = SchoolClass::create(['academic_year_id' => $year->id, 'name' => '2A']);
$teacher = User::factory()->teacher()->create(['email' => 'gradebook-teacher@example.test', 'password' => 'Playwright-grades-1']);
User::factory()->admin()->create(['email' => 'gradebook-admin@example.test', 'password' => 'Playwright-grades-1']);
$assignment = TeacherAssignment::create(['teacher_id' => $teacher->id, 'school_class_id' => $class->id, 'subject_id' => Subject::create(['name' => 'Matematyka'])->id, 'term_id' => $term->id]);
$category = GradeCategory::create(['name' => 'Kartkówka', 'is_active' => true, 'default_weight' => 1]);
$enrollments = collect(range(1, 12))->map(fn ($i) => StudentEnrollment::create(['student_id' => User::factory()->create(['first_name' => 'Jan', 'last_name' => sprintf('Uczeń %02d', $i), ...($i === 1 ? ['email' => 'gradebook-student@example.test', 'password' => 'Playwright-grades-1'] : [])])->id, 'school_class_id' => $class->id, 'starts_on' => '2026-09-01', 'ends_on' => $i === 1 ? '2026-10-31' : null]));
foreach (range(1, 12) as $i) {
    $header = Assessment::create(['school_class_id' => $class->id, 'subject_id' => $assignment->subject_id, 'term_id' => $term->id, 'category_id' => $category->id, 'topic' => 'Kolumna '.$i, 'default_weight' => 1, 'graded_on' => '2026-10-01', 'created_by' => $teacher->id]);
    if ($i <= 4) {
        $value = [4, 5, 5, 3][$i - 1];
        $weight = [2, 1, 3, 1][$i - 1];
        Grade::factory()->create(['assessment_id' => $header->id, 'student_enrollment_id' => $enrollments->first()->id, 'value' => $value, 'weight' => $weight, 'category_id' => $category->id, 'topic' => $header->topic, 'graded_on' => '2026-10-01', 'issued_by' => $teacher->id]);
    }
}
echo json_encode(['assignment' => $assignment->id, 'enrollment' => $enrollments->first()->id]);
