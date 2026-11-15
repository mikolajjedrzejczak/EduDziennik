<?php

namespace Database\Factories;

use App\Models\GradeCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GradeCategory> */
class GradeCategoryFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(), 'default_weight' => 2, 'is_active' => true,
        ];
    }
}
