<?php

namespace App\Http\Requests;

use App\Models\Grade;
use App\Models\GradeCategory;
use App\Services\Grades\GradeFormContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;
use Illuminate\Validation\Validator;

class StoreGradeRequest extends MutationRequest
{
    public function authorize(): bool
    {
        app(GradeFormContext::class)->resolve($this->user(), $this->input('assignment_id'), $this->input('assessment_id'), $this->input('student_enrollment_id'));

        return $this->user()->can('createAny', Grade::class);
    }

    /** @return array<string, array<int, string|In>> */
    public function rules(): array
    {
        [$assignment, , $enrollment] = app(GradeFormContext::class)->resolve($this->user(), $this->input('assignment_id'), $this->input('assessment_id'), $this->input('student_enrollment_id'));
        $term = $assignment->term;
        $start = max($term->starts_on->toDateString(), $enrollment?->starts_on->toDateString() ?? $term->starts_on->toDateString());
        $end = min($term->ends_on->toDateString(), $enrollment?->ends_on?->toDateString() ?? $term->ends_on->toDateString(), now('Europe/Warsaw')->toDateString());

        return [
            'assignment_id' => ['required', 'integer'], 'assessment_id' => ['required', 'integer'], 'student_enrollment_id' => ['required', 'integer'],
            'value' => ['required', 'integer', 'between:1,6'], 'weight' => ['required', 'integer', 'between:1,5'],
            'category_id' => ['required', 'integer', Rule::in(GradeCategory::where('is_active', true)->pluck('id')->all())],
            'topic' => ['required', 'string', 'min:1', 'max:120'], 'comment' => ['nullable', 'string', 'max:500'],
            'graded_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$start, 'before_or_equal:'.$end],
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            [, $assessment, $enrollment] = app(GradeFormContext::class)->resolve($this->user(), $this->input('assignment_id'), $this->input('assessment_id'), $this->input('student_enrollment_id'));
            Gate::authorize('create', [Grade::class, $assessment, $enrollment, $this->input('graded_on')]);
        }];
    }

    public function attributes(): array
    {
        return ['value' => 'ocena', 'weight' => 'waga', 'category_id' => 'kategoria', 'topic' => 'temat', 'comment' => 'komentarz', 'graded_on' => 'data oceny', 'assessment_id' => 'kolumna', 'student_enrollment_id' => 'uczeń', 'assignment_id' => 'dziennik'];
    }
}
