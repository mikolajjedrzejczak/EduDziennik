<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TestHarnessTest extends TestCase
{
    public function test_real_connection_uses_test_schema_and_mariadb_114(): void
    {
        $server = DB::selectOne('SELECT DATABASE() AS db, VERSION() AS version');
        $this->assertSame('edudziennik_test', $server->db);
        $this->assertMatchesRegularExpression('/^11\.4\..*MariaDB/', $server->version);
    }

    public function test_clock_is_fixed_and_timezone_boundary_is_explicit(): void
    {
        $this->assertSame('2026-11-15 12:00:00', now()->utc()->format('Y-m-d H:i:s'));
        $this->travelTo(now()->utc()->setTime(23, 30));
        $this->assertSame('2026-11-16', now('Europe/Warsaw')->toDateString());
        $this->assertSame('2026-11-15', now('UTC')->toDateString());
    }
}
