<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        // Runs before RefreshDatabase's setUpTraits can migrate or reset a schema.
        if (config('database.default') !== 'mariadb' || $app->make('db')->connection()->getDatabaseName() !== 'edudziennik_test') {
            throw new \RuntimeException('Testy wymagają odizolowanej bazy MariaDB edudziennik_test.');
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Feature tests must not depend on a prior `npm run build`.
        $this->withoutVite();
    }
}
