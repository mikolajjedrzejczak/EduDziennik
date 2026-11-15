<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\Users\UserService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ConcurrentUserEditingTest extends TestCase
{
    public function test_two_concurrent_self_degradations_always_leave_an_active_admin(): void
    {
        foreach ([['role', 'teacher'], ['status', 'inactive']] as [$field, $value]) {
            Artisan::call('migrate:fresh', ['--force' => true]);
            $first = User::factory()->admin()->create();
            $second = User::factory()->admin()->create();
            $worker = null;
            DB::beginTransaction();
            try {
                DB::table('domain_locks')->where('key', 'admin_membership')->lockForUpdate()->firstOrFail();
                $worker = new Process([PHP_BINARY, base_path('tests/Infrastructure/concurrent-user-edit.php'), (string) $second->id, $field, $value], base_path(), [
                    'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mariadb', 'DB_DATABASE' => 'edudziennik_test', 'DB_URL' => '',
                ]);
                $worker->setTimeout(15);
                $worker->start();
                $this->assertTrue($worker->waitUntil(fn ($type, $output) => str_contains($output, 'contended')));
                app(UserService::class)->update($first->id, $first->id, [...$first->only(['first_name', 'last_name', 'email', 'role', 'status']), $field => $value]);
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
