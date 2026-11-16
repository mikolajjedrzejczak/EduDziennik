<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.connections.mariadb.database') !== 'edudziennik_test') {
    exit(1);
}
Artisan::call('migrate', ['--force' => true]);
foreach (['student', 'teacher', 'admin'] as $role) {
    $email = "profile-{$role}@example.test";
    if (($argv[1] ?? '') === 'clean') {
        User::withTrashed()->where('email', $email)->forceDelete();

        continue;
    }
    User::updateOrCreate(['email' => $email], [
        'first_name' => 'Anna', 'last_name' => 'Nowak', 'role' => $role, 'status' => 'active',
        'password' => 'Profile-password-123',
    ]);
}
