<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AcademicYear> */
class AcademicYearFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $year = 2026;
        while (AcademicYear::where('name', $year.'/'.($year + 1))->exists()) {
            $year--;
        }

        return [
            'name' => $year.'/'.($year + 1), 'starts_on' => $year.'-09-01', 'ends_on' => ($year + 1).'-08-31',
        ];
    }
}
