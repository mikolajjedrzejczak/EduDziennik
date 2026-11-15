<?php

// Operator-only smoke fixture. Never exposed through an HTTP route.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! $app->environment('local')) {
    throw new RuntimeException('Test wymaga APP_ENV=local.');
}

$connection = config('database.connections.mariadb');
$connection['username'] = 'root';
$connection['password'] = env('MARIADB_ROOT_PASSWORD');
$connection['database'] = null;
config(['database.connections.probe_admin' => $connection]);
$connection['database'] = 'edudziennik_f002_probe';
config(['database.connections.probe' => $connection]);

switch ($argv[1] ?? '') {
    case 'write':
        // Fails if the isolated fixture database already exists; no overwrite.
        DB::connection('probe_admin')->statement('CREATE DATABASE edudziennik_f002_probe');
        if (Artisan::call('migrate', [
            '--database' => 'probe',
            '--path' => 'tests/Infrastructure/migrations',
            '--force' => true,
        ]) !== 0) {
            throw new RuntimeException('Migracja fixture nie powiodła się.');
        }
        DB::connection('probe')->table('f002_probe')->insert(['marker' => 'f002-persistent']);
        break;
    case 'read':
        if (DB::connection('probe')->table('f002_probe')->value('marker') !== 'f002-persistent') {
            throw new RuntimeException('Utracono rekord testowy.');
        }
        break;
    case 'cleanup':
        DB::connection('probe_admin')->statement('DROP DATABASE edudziennik_f002_probe');
        break;
    default:
        throw new RuntimeException('Użyj write, read albo cleanup.');
}
echo "Persistence fixture: OK\n";
