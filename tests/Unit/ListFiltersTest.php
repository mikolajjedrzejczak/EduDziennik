<?php

namespace Tests\Unit;

use App\Support\ListFilters;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ListFiltersTest extends TestCase
{
    public function test_literal_search_escapes_wildcards_and_backslash(): void
    {
        $this->assertSame('%Żółć\\%\\_\\\\%', ListFilters::contains(' Żółć%_\\ '));
    }

    public function test_sort_and_parameter_shapes_are_allowlisted(): void
    {
        foreach ([['sort' => 'email desc; DROP TABLE users'], ['sort' => ['email']], ['direction' => ['asc']], ['q' => ['Nowak']], ['per_page' => 100], ['page' => 0]] as $input) {
            $this->assertTrue(Validator::make($input, ListFilters::rules(['last_name', 'email']))->fails());
        }
        $this->assertFalse(Validator::make(['q' => 'Żółć%_', 'sort' => 'email', 'direction' => 'desc', 'page' => 2, 'per_page' => 25], ListFilters::rules(['last_name', 'email']))->fails());
    }
}
