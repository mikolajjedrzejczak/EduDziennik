<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TestDatabase;

class TestDatabaseTest extends TestCase
{
    /** @param array<string, mixed> $connection */
    #[DataProvider('unsafeContexts')]
    public function test_unsafe_context_is_rejected_before_any_database_operation(string $environment, string $default, array $connection): void
    {
        $this->expectException(RuntimeException::class);
        TestDatabase::assertSafe($environment, $default, $connection);
    }

    public static function unsafeContexts(): array
    {
        $safe = ['driver' => 'mariadb', 'database' => 'edudziennik_test'];

        return [
            'production' => ['production', 'mariadb', $safe],
            'local' => ['local', 'mariadb', $safe],
            'sqlite default' => ['testing', 'sqlite', $safe],
            'sqlite driver' => ['testing', 'mariadb', ['driver' => 'sqlite', 'database' => 'edudziennik_test']],
            'development database' => ['testing', 'mariadb', ['driver' => 'mariadb', 'database' => 'edudziennik']],
            'missing database' => ['testing', 'mariadb', ['driver' => 'mariadb']],
            'read database' => ['testing', 'mariadb', [...$safe, 'read' => ['database' => 'edudziennik']]],
            'write database' => ['testing', 'mariadb', [...$safe, 'write' => ['database' => 'edudziennik']]],
        ];
    }

    public function test_dedicated_mariadb_context_is_accepted(): void
    {
        TestDatabase::assertSafe('testing', 'mariadb', ['driver' => 'mariadb', 'database' => 'edudziennik_test']);
        $this->expectNotToPerformAssertions();
    }
}
