<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

// F-007 Playwright fixture: three role accounts in the isolated test database only.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.connections.mariadb.database') !== 'edudziennik_test') {
    fwrite(STDERR, "Refusing to seed outside edudziennik_test.\n");
    exit(1);
}
Artisan::call('migrate', ['--force' => true]);
DB::statement('SET FOREIGN_KEY_CHECKS=0');
DB::table('users')->truncate();
DB::statement('SET FOREIGN_KEY_CHECKS=1');
$password = Hash::make('Playwright-shell-1');
foreach ([
    ['student', 'Jan', 'Kowalski-Wiśniewski-Nowakowski-Zieliński-Jabłoński'],
    ['teacher', 'Anna', 'Nowak'],
    ['admin', 'Marta', 'Zielińska'],
] as [$role, $first, $last]) {
    User::query()->create([
        'first_name' => $first, 'last_name' => $last, 'email' => "shell-{$role}@example.test",
        'password' => $password, 'role' => $role, 'status' => 'active',
    ]);
}
echo "Seeded shell users.\n";
