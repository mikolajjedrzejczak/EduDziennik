<?php

namespace Tests\Feature\Database;

use App\Models\AcademicYear;
use App\Models\GradeCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CalendarConfigurationTest extends TestCase
{
    use RefreshDatabase;

    private function configuration(): array
    {
        return ['years' => [['name' => '2026/2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-08-31', 'terms' => [
            ['number' => 1, 'starts_on' => '2026-09-01', 'ends_on' => '2027-01-31'],
            ['number' => 2, 'starts_on' => '2027-02-01', 'ends_on' => '2027-08-31'],
        ]]], 'categories' => [['name' => 'Quiz', 'default_weight' => 2, 'is_active' => true]]];
    }

    private function runConfiguration(array $data, int $expected): void
    {
        $file = tempnam(sys_get_temp_dir(), 'f003-calendar-');
        file_put_contents($file, json_encode($data, JSON_THROW_ON_ERROR));
        try {
            $this->artisan('school:configure', ['file' => $file])->assertExitCode($expected);
        } finally {
            unlink($file);
        }
    }

    public function test_operator_can_create_configuration_and_cannot_overwrite_year(): void
    {
        $this->runConfiguration($this->configuration(), 0);
        $this->assertDatabaseCount('academic_years', 1);
        $this->assertDatabaseCount('terms', 2);
        $this->runConfiguration($this->configuration(), 1);
        $this->assertDatabaseCount('academic_years', 1);
    }

    #[DataProvider('invalidConfigurations')]
    public function test_invalid_file_leaves_no_partial_configuration(string $path, mixed $value): void
    {
        $data = $this->configuration();
        data_set($data, $path, $value);
        $this->runConfiguration($data, 1);
        $this->assertSame(0, AcademicYear::count());
        $this->assertSame(0, GradeCategory::count());
    }

    public static function invalidConfigurations(): array
    {
        return [
            ['years.0.terms.0.starts_on', '2026-08-31'], ['years.0.terms.1.ends_on', '2027-09-01'],
            ['years.0.terms.1.starts_on', '2027-01-31'], ['years.0.terms.1.number', 1],
            ['years.0.terms.1.number', 1.5], ['years.0.terms.1.closed_at', null],
            ['categories.0.default_weight', 0], ['categories.0.default_weight', 2.5],
            ['categories.0.is_active', 'yes'], ['categories.0.is_active', 1], ['categories.0.name', []],
            ['close_term_ids', [9999]], ['reopen_term_ids', [1]],
        ];
    }

    public function test_invalid_json_and_missing_file_return_failure(): void
    {
        $this->artisan('school:configure', ['file' => '/does-not-exist-f003.json'])->assertExitCode(1);
        $file = tempnam(sys_get_temp_dir(), 'f003-json-');
        file_put_contents($file, '{invalid json');
        try {
            $this->artisan('school:configure', ['file' => $file])->expectsOutput('Nieprawidłowy JSON konfiguracji.')->assertExitCode(1);
        } finally {
            unlink($file);
        }
    }
}
