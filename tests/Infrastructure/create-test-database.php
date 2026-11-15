<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('local')) {
    throw new RuntimeException('Tworzenie bazy testowej wymaga APP_ENV=local.');
}
$connection = config('database.connections.mariadb');
$connection['username'] = 'root';
$connection['password'] = env('MARIADB_ROOT_PASSWORD');
$connection['database'] = null;
$connection['url'] = null;
config(['database.connections.test_admin' => $connection]);
DB::connection('test_admin')->statement('CREATE DATABASE edudziennik_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$user = DB::connection('test_admin')->getPdo()->quote((string) config('database.connections.mariadb.username'));
DB::connection('test_admin')->statement("GRANT ALL PRIVILEGES ON edudziennik_test.* TO {$user}@'%'");
echo "Utworzono odizolowaną bazę edudziennik_test.\n";
