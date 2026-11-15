<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\Users\UserService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ConcurrentUserDeletionTest extends TestCase
{
    public function test_cross_deletes_and_edit_delete_race_leave_an_active_admin(): void
    {
        foreach (['delete', 'deactivate'] as $operation) {
            Artisan::call('migrate:fresh', ['--force' => true]);
            $first = User::factory()->admin()->create();
            $second = User::factory()->admin()->create();
            $worker = null;
            DB::beginTransaction();
            try {
                DB::table('domain_locks')->where('key', 'admin_membership')->lockForUpdate()->firstOrFail();
                $worker = new Process([PHP_BINARY, base_path('tests/Infrastructure/concurrent-user-delete.php'), (string) $second->id, (string) $first->id, $first->last_name], base_path(), [
                    'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mariadb', 'DB_DATABASE' => 'edudziennik_test', 'DB_URL' => '',
                ]);
                $worker->setTimeout(15);
                $worker->start();
                $this->assertTrue($worker->waitUntil(fn ($type, $output) => str_contains($output, 'contended')));
                if ($operation === 'delete') {
                    app(UserService::class)->delete($first->id, $second->id, $second->last_name);
                } else {
                    app(UserService::class)->update($first->id, $second->id, [...$second->only(['first_name', 'last_name', 'email', 'role', 'status']), 'status' => 'inactive']);
                }
                DB::commit();
                $this->assertSame(0, $worker->wait(), $worker->getErrorOutput());
                $this->assertStringContainsString('rejected', $worker->getOutput());
                $this->assertSame(1, User::where('role', 'admin')->where('status', 'active')->count());
            } finally {
                if (DB::transactionLevel() > 0) {
                    DB::rollBack();
                }
                $worker?->stop();
                Artisan::call('migrate:fresh', ['--force' => true]);
            }
        }
    }
}
