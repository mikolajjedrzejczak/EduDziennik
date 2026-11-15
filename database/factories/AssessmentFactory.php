<?php

namespace Database\Factories;

use App\Models\Assessment;
use App\Models\GradeCategory;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Assessment> */
class AssessmentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'school_class_id' => SchoolClass::factory(), 'subject_id' => Subject::factory(),
            'term_id' => fn (array $attributes) => Term::factory()->create(['academic_year_id' => SchoolClass::findOrFail((int) $attributes['school_class_id'])->academic_year_id])->id,
            'category_id' => GradeCategory::factory(), 'topic' => 'Testowa kolumna', 'default_weight' => 2, 'graded_on' => fn (array $attributes) => Term::findOrFail((int) $attributes['term_id'])->starts_on->addMonth()->toDateString(), 'created_by' => User::factory()->admin(),
        ];
    }
}
