<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\GradeCategory;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use App\Services\Grades\GradeService;
use App\Services\School\AssessmentService;
use App\Services\School\AssignmentService;
use App\Services\School\CalendarService;
use App\Services\School\EnrollmentService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Dane demonstracyjne są dozwolone wyłącznie lokalnie lub w testach.');
        }
        $password = config('demo.password');
        $date = config('demo.date');
        if (! is_string($password) || mb_strlen($password) < 12 || $date !== '2026-11-15') {
            throw new RuntimeException('Wymagane DEMO_PASSWORD (minimum 12 znaków) i DEMO_DATE=2026-11-15.');
        }
        if (User::withTrashed()->exists() || AcademicYear::exists() || GradeCategory::exists() || Subject::withTrashed()->exists()) {
            throw new RuntimeException('Seeder wymaga pustej, dedykowanej bazy demonstracyjnej.');
        }
        // The explicit fixture clock applies only for this operation; restore an existing test clock.
        $previous = CarbonImmutable::getTestNow();
        CarbonImmutable::setTestNow($date.' 12:00:00 UTC');
        $previousMutable = Carbon::getTestNow();
        Carbon::setTestNow($date.' 12:00:00 UTC');
        try {
            DB::transaction(function () use ($password) {
                $hash = Hash::make($password);
                $admins = collect([1, 2])->map(fn ($i) => User::create(['first_name' => 'Admin', 'last_name' => 'Demo '.$i, 'email' => 'admin'.$i.'@example.test', 'password' => $hash, 'role' => 'admin', 'status' => 'active']));
                $teachers = collect([1, 2, 3])->map(fn ($i) => User::create(['first_name' => 'Nauczyciel', 'last_name' => 'Demo '.$i, 'email' => 'teacher'.$i.'@example.test', 'password' => $hash, 'role' => 'teacher', 'status' => 'active']));
                $students = collect(range(1, 18))->map(fn ($i) => User::create(['first_name' => 'Uczeń', 'last_name' => 'Demo '.$i, 'email' => 'student'.$i.'@example.test', 'password' => $hash, 'role' => 'student', 'status' => 'active']));
                app(CalendarService::class)->configure([
                    'years' => [
                        ['name' => '2025/2026', 'starts_on' => '2025-09-01', 'ends_on' => '2026-08-31', 'terms' => [
                            ['number' => 1, 'starts_on' => '2025-09-01', 'ends_on' => '2026-01-31'],
                            ['number' => 2, 'starts_on' => '2026-02-01', 'ends_on' => '2026-08-31'],
                        ]],
                        ['name' => '2026/2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-08-31', 'terms' => [
                            ['number' => 1, 'starts_on' => '2026-09-01', 'ends_on' => '2027-01-31'],
                            ['number' => 2, 'starts_on' => '2027-02-01', 'ends_on' => '2027-08-31'],
                        ]],
                    ],
                    'categories' => [
                        ['name' => 'Quiz', 'default_weight' => 2, 'is_active' => true],
                        ['name' => 'Test', 'default_weight' => 3, 'is_active' => true],
                        ['name' => 'Homework', 'default_weight' => 1, 'is_active' => true],
                        ['name' => 'Activity', 'default_weight' => 1, 'is_active' => true],
                    ],
                ]);
                $year = AcademicYear::where('name', '2026/2027')->firstOrFail();
                $term = $year->terms()->where('number', 1)->firstOrFail();
                $subjects = collect(['Matematyka', 'Język polski', 'Historia', 'Biologia'])->map(fn ($name) => Subject::create(['name' => $name]));
                $classes = collect(['1A', '2A', '2B'])->map(fn ($name) => SchoolClass::create(['name' => $name, 'academic_year_id' => $year->id]));
                $memberships = $students->map(fn ($student, $i) => app(EnrollmentService::class)->enroll($student->id, $classes[intdiv($i, 6)]->id, '2026-09-01'));
                foreach ([[$teachers[0], $classes[1], $subjects[0]], [$teachers[0], $classes[1], $subjects[1]], [$teachers[1], $classes[0], $subjects[2]]] as [$teacher, $class, $subject]) {
                    app(AssignmentService::class)->assign($teacher->id, $class->id, $subject->id, $term->id);
                }
                $revoked = app(AssignmentService::class)->assign($teachers[1]->id, $classes[2]->id, $subjects[0]->id, $term->id);
                $revoked->update(['revoked_at' => now()]);
                $category = GradeCategory::where('name', 'Quiz')->firstOrFail();
                $service = app(GradeService::class);
                $headers = [];
                foreach ([[4, 2], [5, 1], [5, 3], [3, 1]] as $i => [$value, $weight]) {
                    $header = app(AssessmentService::class)->create($teachers[0]->id, ['school_class_id' => $classes[1]->id, 'subject_id' => $subjects[0]->id, 'term_id' => $term->id, 'category_id' => $category->id, 'topic' => 'Fixture '.($i + 1), 'default_weight' => $weight, 'graded_on' => '2026-10-'.(10 + $i)]);
                    $headers[] = $header;
                    $service->create($teachers[0]->id, $memberships[6]->id, $header->id, ['value' => $value]);
                }
                // Independent audit scenario; it does not change student7's 31/7 fixture.
                $changed = $service->create($teachers[0]->id, $memberships[7]->id, $headers[0]->id, ['value' => 3]);
                $changed = $service->update($teachers[0]->id, $changed->id, 1, 'Korekta demonstracyjna', ['value' => 4]);
                $service->delete($teachers[0]->id, $changed->id, $changed->version, 'Usunięcie demonstracyjne');
                $historicYear = AcademicYear::where('name', '2025/2026')->firstOrFail();
                $historicClass = SchoolClass::create(['academic_year_id' => $historicYear->id, 'name' => '1A']);
                $historicTerm = $historicYear->terms()->where('number', 1)->firstOrFail();
                $historicEnrollment = app(EnrollmentService::class)->enroll($students[6]->id, $historicClass->id, '2025-09-01', '2026-08-31');
                $historicHeader = app(AssessmentService::class)->create($admins[0]->id, ['school_class_id' => $historicClass->id, 'subject_id' => $subjects[0]->id, 'term_id' => $historicTerm->id, 'category_id' => $category->id, 'topic' => 'Archiwum', 'default_weight' => 5, 'graded_on' => '2025-10-10']);
                $service->create($admins[0]->id, $historicEnrollment->id, $historicHeader->id, ['value' => 4, 'weight' => 4]);
                app(CalendarService::class)->configure(['years' => [], 'categories' => [], 'close_term_ids' => $historicYear->terms()->pluck('id')->all()]);
                $students[16]->update(['status' => 'inactive']);
                $students[17]->update(['status' => 'inactive']);
                $students[17]->delete();
            });
        } finally {
            CarbonImmutable::setTestNow($previous);
            Carbon::setTestNow($previousMutable);
        }
    }
}
