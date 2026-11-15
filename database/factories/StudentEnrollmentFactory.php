<?php

namespace Database\Factories;

use App\Models\SchoolClass;
use App\Models\StudentEnrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StudentEnrollment> */
class StudentEnrollmentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'student_id' => User::factory(), 'school_class_id' => SchoolClass::factory(),
            'starts_on' => fn (array $attributes) => SchoolClass::findOrFail((int) $attributes['school_class_id'])->academicYear->starts_on->toDateString(),
            'ends_on' => null,
        ];
    }
}
