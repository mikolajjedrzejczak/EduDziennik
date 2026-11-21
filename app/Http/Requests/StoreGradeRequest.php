<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesGradeFormContext;
use App\Http\Requests\Concerns\ValidatesGradeFields;
use App\Models\Grade;
use App\Support\ListFilters;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class StoreGradeRequest extends MutationRequest
{
    use ResolvesGradeFormContext;
    use ValidatesGradeFields;

    public function authorize(): bool
    {
        $this->gradeFormContext();

        return $this->user()->can('createAny', Grade::class);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        [$assignment, , $enrollment] = $this->gradeFormContext();
        $term = $assignment->term;
        $start = max($term->starts_on->toDateString(), $enrollment?->starts_on->toDateString() ?? $term->starts_on->toDateString());
        $end = min($term->ends_on->toDateString(), $enrollment?->ends_on?->toDateString() ?? $term->ends_on->toDateString(), now('Europe/Warsaw')->toDateString());

        return [
            ...ListFilters::gradebookRules(),
            'assignment_id' => ['required', 'integer'], 'assessment_id' => ['required', 'integer'], 'student_enrollment_id' => ['required', 'integer'],
            ...$this->gradeFieldRules($start, $end),
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            [$assignment, $assessment, $enrollment] = $this->gradeFormContext();
            if ($assignment->term->closed_at !== null) {
                $validator->errors()->add('form', 'Semestr jest zamknięty. Nie można wystawić oceny.');

                return;
            }
            Gate::authorize('create', [Grade::class, $assessment, $enrollment, $this->input('graded_on')]);
        }];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [...$this->gradeFieldAttributes(), 'assessment_id' => 'kolumna', 'student_enrollment_id' => 'uczeń', 'assignment_id' => 'dziennik'];
    }
}
