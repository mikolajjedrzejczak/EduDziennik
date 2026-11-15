<?php

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
echo "Seeded student grades demo data.\n";
