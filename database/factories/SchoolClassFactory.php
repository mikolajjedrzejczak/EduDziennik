<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SchoolClass> */
class SchoolClassFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'academic_year_id' => AcademicYear::factory(), 'name' => fake()->unique()->bothify('?#'),
        ];
    }
}
