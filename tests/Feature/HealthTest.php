<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_health_checks_database_without_requiring_tables(): void
    {
        DB::shouldReceive('select')->once()->with('SELECT 1')->andReturn([]);

        $this->get('/up')->assertOk();
    }

    public function test_database_failure_returns_service_unavailable_without_connection_details(): void
    {
        DB::shouldReceive('select')->once()->with('SELECT 1')
            ->andThrow(new RuntimeException('private-connection-secret'));

        $this->get('/up')->assertServiceUnavailable()
            ->assertDontSee('private-connection-secret');
    }
}
