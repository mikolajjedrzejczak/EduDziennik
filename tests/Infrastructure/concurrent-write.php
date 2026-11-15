<?php

// A second PHP process for MariaDB lock tests; no shell interpolation or secrets in arguments.
use App\Models\Assessment;
use App\Services\Grades\GradeService;
use App\Services\School\EnrollmentService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'mariadb' || DB::connection()->getDatabaseName() !== 'edudziennik_test') {
    throw new RuntimeException('Worker wymaga odizolowanej bazy testowej MariaDB.');
}
// NOWAIT proves the parent holds the actual serializing lock before it commits.
$table = ($argv[1] ?? '') === 'enroll' ? 'users' : 'terms';
$id = $table === 'users' ? (int) $argv[2] : Assessment::findOrFail((int) $argv[4])->term_id;
DB::beginTransaction();
try {
    DB::select("SELECT id FROM $table WHERE id = ? FOR UPDATE NOWAIT", [$id]);
    throw new RuntimeException('Brak oczekiwanej blokady procesu nadrzędnego.');
} catch (QueryException $exception) {
    if (($exception->errorInfo[1] ?? null) !== 1205) {
        throw $exception;
    }
} finally {
    DB::rollBack();
}
fwrite(STDOUT, "contended\n");
fflush(STDOUT);
try {
    if (($argv[1] ?? '') === 'enroll') {
        app(EnrollmentService::class)->enroll((int) $argv[2], (int) $argv[3], '2026-10-01');
    } elseif (($argv[1] ?? '') === 'grade') {
        app(GradeService::class)->create((int) $argv[2], (int) $argv[3], (int) $argv[4], ['value' => 4]);
    } else {
        throw new RuntimeException('Nieznana operacja.');
    }
} catch (ValidationException) {
    fwrite(STDOUT, "rejected\n");
    exit(0);
}
fwrite(STDOUT, "unexpected-write\n");
exit(1);
