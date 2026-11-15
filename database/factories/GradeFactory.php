<?php

namespace Database\Factories;

use App\Models\Assessment;
use App\Models\Grade;
use App\Models\StudentEnrollment;
use App\Services\Grades\GradeService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/** @extends Factory<Grade> */
class GradeFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'assessment_id' => Assessment::factory(),
            'student_enrollment_id' => fn (array $attributes) => StudentEnrollment::factory()->create(['school_class_id' => Assessment::findOrFail((int) $attributes['assessment_id'])->school_class_id])->id,
            'category_id' => fn (array $attributes) => Assessment::findOrFail((int) $attributes['assessment_id'])->category_id,
            'value' => 4, 'weight' => 2, 'topic' => 'Testowa ocena', 'comment' => null,
            'graded_on' => fn (array $attributes) => Assessment::findOrFail((int) $attributes['assessment_id'])->graded_on->toDateString(),
            'issued_by' => fn (array $attributes) => Assessment::findOrFail((int) $attributes['assessment_id'])->created_by,
        ];
    }

    /**
     * Persist through the same service as production, including authorization and audit.
     *
     * @param  (callable(array<string, mixed>): array<string, mixed>)|array<string, mixed>  $attributes
     * @return Collection<int, Grade>|Grade
     */
    public function create($attributes = [], ?Model $parent = null)
    {
        return DB::transaction(function () use ($attributes) {
            if ($this->count !== null) {
                $models = [];
                for ($i = 0; $i < $this->count; $i++) {
                    $model = $this->count(null)->create($attributes);
                    assert($model instanceof Grade);
                    $models[] = $model;
                }

                return $this->newModel()->newCollection($models);
            }
            $grade = $this->make($attributes);
            assert($grade instanceof Grade);

            return app(GradeService::class)->create(
                (int) $grade->issued_by, (int) $grade->student_enrollment_id, (int) $grade->assessment_id,
                [...$grade->only(['category_id', 'value', 'weight', 'topic', 'comment']), 'graded_on' => $grade->graded_on->toDateString()],
            );
        });
    }
}
