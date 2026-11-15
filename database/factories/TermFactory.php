<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\Term;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Term> */
class TermFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'academic_year_id' => AcademicYear::factory(), 'number' => 1, 'starts_on' => fn (array $attributes) => AcademicYear::findOrFail((int) $attributes['academic_year_id'])->starts_on->toDateString(),
            'ends_on' => fn (array $attributes) => AcademicYear::findOrFail((int) $attributes['academic_year_id'])->starts_on->addMonths(4)->endOfMonth()->toDateString(),
        ];
    }
}
