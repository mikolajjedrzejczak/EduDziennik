<?php

use App\Models\AcademicYear;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Support\TestDatabase;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
TestDatabase::assertSafe($app->environment(), config('database.default'), DB::connection()->getConfig());
Artisan::call('migrate:fresh', ['--force' => true]);
AcademicYear::create(['name' => '2026/2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-08-31']);
$password = Hash::make('Playwright-classes-1');
User::create(['first_name' => 'Marta', 'last_name' => 'Testowa', 'email' => 'classes-admin@example.test', 'password' => $password, 'role' => 'admin', 'status' => 'active']);
foreach ([390, 1024, 1440] as $width) {
    User::create(['first_name' => 'Uczeń', 'last_name' => 'Testowy '.$width, 'email' => "classes-student-{$width}@example.test", 'password' => $password, 'role' => 'student', 'status' => 'active']);
}
echo "Seeded class management fixtures.\n";
