<?php

namespace Tests\Unit;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestClock;

class TestClockTest extends TestCase
{
    public function test_fixture_instant_has_an_explicit_timezone(): void
    {
        $clock = CarbonImmutable::parse(TestClock::NOW);
        $this->assertSame('2026-11-15T12:00:00+00:00', $clock->toIso8601String());
        $this->assertSame('2026-11-15 13:00:00', $clock->setTimezone('Europe/Warsaw')->format('Y-m-d H:i:s'));
    }
}
