<?php

namespace Tests\Support;

use RuntimeException;

final class TestDatabase
{
    /** @param array<string, mixed> $connection */
    public static function assertSafe(string $environment, string $default, array $connection): void
    {
        if ($environment !== 'testing' || $default !== 'mariadb'
            || ($connection['driver'] ?? null) !== 'mariadb'
            || ($connection['database'] ?? null) !== 'edudziennik_test') {
            throw new RuntimeException('Testy wymagają APP_ENV=testing i odizolowanej bazy MariaDB edudziennik_test.');
        }

        // Split read/write connections must never reach another schema either.
        foreach (['read', 'write'] as $mode) {
            if (isset($connection[$mode]['database']) && $connection[$mode]['database'] !== 'edudziennik_test') {
                throw new RuntimeException('Połączenia read/write muszą korzystać z edudziennik_test.');
            }
        }
    }
}
