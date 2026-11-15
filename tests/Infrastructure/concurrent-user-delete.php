<?php

use App\Services\Users\UserService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestDatabase;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
TestDatabase::assertSafe($app->environment(), config('database.default'), DB::connection()->getConfig());
DB::beginTransaction();
try {
    DB::select("SELECT `key` FROM domain_locks WHERE `key` = 'admin_membership' FOR UPDATE NOWAIT");
    throw new RuntimeException('Expected a contended domain lock.');
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
    app(UserService::class)->delete((int) $argv[1], (int) $argv[2], $argv[3]);
} catch (AuthorizationException) {
    fwrite(STDOUT, "rejected\n");
    exit(0);
}
fwrite(STDOUT, "unexpected-write\n");
exit(1);
