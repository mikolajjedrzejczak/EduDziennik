<?php

// A second PHP process for MariaDB lock tests; no shell interpolation or secrets in arguments.
use App\Models\Assessment;
use App\Services\Grades\GradeService;
use App\Services\School\EnrollmentService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\TestClock;
use Tests\Support\TestDatabase;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
TestDatabase::assertSafe($app->environment(), config('database.default'), DB::connection()->getConfig());
Carbon::setTestNow(TestClock::NOW);
// NOWAIT proves the parent holds the actual serializing lock before it commits.
$table = in_array($argv[1] ?? '', ['enroll', 'transfer'], true) ? 'users' : 'terms';
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
    } elseif (($argv[1] ?? '') === 'transfer') {
        app(EnrollmentService::class)->enroll((int) $argv[2], (int) $argv[3], '2026-11-15', null, (int) $argv[4]);
    } elseif (($argv[1] ?? '') === 'grade-update') {
        app(GradeService::class)->update((int) $argv[2], (int) $argv[3], 1, 'Korekta konkurencyjna', ['value' => 6]);
    } elseif (($argv[1] ?? '') === 'grade') {
        app(GradeService::class)->create((int) $argv[2], (int) $argv[3], (int) $argv[4], ['value' => 4]);
    } else {
        throw new RuntimeException('Nieznana operacja.');
    }
} catch (ValidationException|AuthorizationException $exception) {
    if (($argv[1] ?? '') === 'grade-update' && (! $exception instanceof ValidationException || ! array_key_exists('conflict', $exception->errors()))) {
        throw $exception;
    }
    fwrite(STDOUT, "rejected\n");
    exit(0);
}
fwrite(STDOUT, "unexpected-write\n");
exit(1);
