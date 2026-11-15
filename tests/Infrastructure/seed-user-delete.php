<?php

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Support\TestDatabase;

// F-305 Playwright fixture: isolated edudziennik_test database only.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
TestDatabase::assertSafe($app->environment(), config('database.default'), DB::connection()->getConfig());
Artisan::call('migrate:fresh', ['--force' => true]);
$year = AcademicYear::create(['name' => '2026/2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-08-31']);
Term::create(['academic_year_id' => $year->id, 'number' => 1, 'starts_on' => '2026-09-01', 'ends_on' => '2027-01-31']);
SchoolClass::create(['academic_year_id' => $year->id, 'name' => '2A']);
Subject::create(['name' => 'Matematyka']);
$password = Hash::make('Playwright-create-1');
User::create(['first_name' => 'Marta', 'last_name' => 'Testowa', 'email' => 'create-admin@example.test', 'password' => $password, 'role' => 'admin', 'status' => 'active']);
User::create(['first_name' => 'Anna', 'last_name' => 'Nowak', 'email' => 'create-existing@example.test', 'password' => $password, 'role' => 'teacher', 'status' => 'active']);
for ($index = 0; $index < 4; $index++) {
    User::create(['first_name' => 'Anna', 'last_name' => 'Nowak', 'email' => "delete-target-{$index}@example.test", 'password' => $password, 'role' => 'teacher', 'status' => 'active']);
}
echo "Seeded user-deletion fixtures.\n";
