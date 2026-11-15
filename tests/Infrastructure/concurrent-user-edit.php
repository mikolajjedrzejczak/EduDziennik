<?php

use App\Models\User;
use App\Services\Users\UserService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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
$admin = User::findOrFail((int) $argv[1]);
try {
    app(UserService::class)->update($admin->id, $admin->id, [...$admin->only(['first_name', 'last_name', 'email', 'role', 'status']), $argv[2] => $argv[3]]);
} catch (ValidationException) {
    fwrite(STDOUT, "rejected\n");
    exit(0);
}
fwrite(STDOUT, "unexpected-write\n");
exit(1);
