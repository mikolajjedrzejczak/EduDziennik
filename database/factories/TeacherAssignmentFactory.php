<?php

namespace Database\Factories;

use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Models\Term;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TeacherAssignment> */
class TeacherAssignmentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'teacher_id' => User::factory()->teacher(), 'school_class_id' => SchoolClass::factory(), 'subject_id' => Subject::factory(),
            'term_id' => fn (array $attributes) => Term::factory()->create(['academic_year_id' => SchoolClass::findOrFail((int) $attributes['school_class_id'])->academic_year_id])->id,
        ];
    }
}
