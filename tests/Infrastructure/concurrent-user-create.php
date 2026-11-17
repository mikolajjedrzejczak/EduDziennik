<?php

use App\Http\Requests\Admin\StoreUserRequest;
use App\Models\User;
use App\Services\Users\UserService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\TestDatabase;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
TestDatabase::assertSafe($app->environment(), config('database.default'), DB::connection()->getConfig());
$actor = User::findOrFail((int) $argv[1]);
$request = new StoreUserRequest;
$request->setUserResolver(fn () => $actor);
if (! $request->authorize()) {
    throw new RuntimeException('Preflight must succeed before revocation.');
}

// A NOWAIT probe proves that the parent holds the same serialization lock.
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
fwrite(STDOUT, "authorized-and-contended\n");
fflush(STDOUT);
// Parent commits revocation before releasing this barrier; no timing assumptions.
if (trim((string) fgets(STDIN)) !== 'committed') {
    throw new RuntimeException('Missing revocation commit barrier.');
}
$data = json_decode($argv[2], true, flags: JSON_THROW_ON_ERROR);
try {
    app(UserService::class)->create($actor->id, $data['account'], $data['enrollment'], $data['assignments']);
} catch (AuthorizationException $exception) {
    fwrite(STDOUT, "rejected\n");
    exit(0);
} catch (HttpException $exception) {
    if ($exception->getStatusCode() !== 403) {
        throw $exception;
    }
    fwrite(STDOUT, "rejected\n");
    exit(0);
}
fwrite(STDOUT, "unexpected-write\n");
exit(1);
