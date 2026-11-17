<?php

use App\Models\Grade;
use App\Models\Subject;
use App\Models\User;
use App\Services\Grades\GradeService;
use App\Services\School\AssessmentService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

// F-102 Playwright fixture: the documented demo data set (student7 = Math 4,43) in the isolated
// test database only. `clean` wipes it again so other specs find the tables empty.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.connections.mariadb.database') !== 'edudziennik_test') {
    fwrite(STDERR, "Refusing to seed outside edudziennik_test.\n");
    exit(1);
}
if (in_array($argv[1] ?? '', ['detail-ids', 'history-ids'], true)) {
    $query = Grade::withTrashed()->whereHas('enrollment.student', fn ($q) => $q->where('email', 'student7@example.test'));
    if (! (clone $query)->whereNotNull('deleted_at')->exists()) {
        // F-103 needs an archived own grade; create its audit through the domain service.
        $grade = (clone $query)->whereNull('deleted_at')->firstOrFail();
        if (($argv[1] ?? '') === 'history-ids') {
            $grade = app(GradeService::class)->update($grade->issued_by, $grade->id, $grade->version, 'Fixture: korekta punktów', ['value' => $grade->value === 5 ? 4 : 5, 'comment' => 'Po korekcie']);
        }
        app(GradeService::class)->delete($grade->issued_by, $grade->id, $grade->version, 'Fixture: błędny wpis');
    }
    echo json_encode(['active' => (clone $query)->whereNull('deleted_at')->value('id'), 'deleted' => (clone $query)->whereNotNull('deleted_at')->value('id')]);
    exit(0);
}
Artisan::call('migrate', ['--force' => true]);
DB::statement('SET FOREIGN_KEY_CHECKS=0');
foreach (['grade_audits', 'grades', 'assessments', 'teacher_assignments', 'student_enrollments', 'school_classes', 'terms', 'academic_years', 'subjects', 'grade_categories', 'users'] as $table) {
    DB::table($table)->truncate();
}
DB::statement('SET FOREIGN_KEY_CHECKS=1');
if (($argv[1] ?? '') === 'clean') {
    echo "Cleaned domain tables.\n";
    exit(0);
}
config(['demo.password' => 'Playwright-grades-1', 'demo.date' => '2026-11-15']);
Artisan::call('db:seed', ['--force' => true]);
if (($argv[1] ?? '') === 'many-subjects') {
    $base = Grade::whereHas('enrollment.student', fn ($q) => $q->where('email', 'student7@example.test'))->with('assessment')->firstOrFail();
    $admin = User::where('role', 'admin')->firstOrFail();
    for ($i = 1; $i <= 11; $i++) {
        $subject = Subject::create(['name' => sprintf('Przedmiot mobilny %02d', $i)]);
        $assessment = app(AssessmentService::class)->create($admin->id, [
            'school_class_id' => $base->assessment->school_class_id,
            'subject_id' => $subject->id,
            'term_id' => $base->assessment->term_id,
            'category_id' => $base->category_id,
            'topic' => 'Mobilna ocena '.$i,
            'default_weight' => 2,
            'graded_on' => '2026-10-10',
        ]);
        app(GradeService::class)->create($admin->id, $base->student_enrollment_id, $assessment->id, ['value' => 4, 'weight' => 2]);
    }
}
echo "Seeded student grades demo data.\n";
