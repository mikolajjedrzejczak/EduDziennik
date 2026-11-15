<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Carbon;
use Tests\Support\TestClock;
use Tests\Support\TestDatabase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        // Runs before RefreshDatabase's setUpTraits can migrate or reset a schema.
        TestDatabase::assertSafe($app->environment(), config('database.default'), $app->make('db')->connection()->getConfig());

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Feature tests must not depend on a prior `npm run build`.
        $this->withoutVite();
        $this->travelTo(Carbon::parse(TestClock::NOW));
    }
}
