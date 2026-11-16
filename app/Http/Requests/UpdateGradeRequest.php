<?php

namespace App\Http\Requests;

use App\Models\GradeCategory;
use App\Services\Grades\GradeFormContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateGradeRequest extends MutationRequest
{
    public function authorize(): bool
    {
        $grade = $this->route('grade');
        Gate::authorize('view', $grade);
        app(GradeFormContext::class)->resolve($this->user(), $this->input('assignment_id'), $grade->assessment_id, $grade->student_enrollment_id);
        Gate::authorize('update', $grade);

        return true;
    }

    public function rules(): array
    {
        $grade = $this->route('grade');
        $term = $grade->assessment->term;
        $enrollment = $grade->enrollment;
        $start = max($term->starts_on->toDateString(), $enrollment->starts_on->toDateString());
        $end = min($term->ends_on->toDateString(), ($enrollment->ends_on ?? $enrollment->schoolClass->academicYear->ends_on)->toDateString(), now('Europe/Warsaw')->toDateString());

        return [
            'assignment_id' => ['required', 'integer'],
            'version' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'value' => ['required', 'integer', 'between:1,6'],
            'weight' => ['required', 'integer', 'between:1,5'],
            'category_id' => ['required', 'integer', Rule::in(GradeCategory::where('is_active', true)->pluck('id')->all())],
            'topic' => ['required', 'string', 'min:1', 'max:120'],
            'comment' => ['nullable', 'string', 'max:500'],
            'graded_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$start, 'before_or_equal:'.$end],
        ];
    }

    public function attributes(): array
    {
        return ['value' => 'ocena', 'weight' => 'waga', 'category_id' => 'kategoria', 'topic' => 'temat', 'comment' => 'komentarz', 'graded_on' => 'data oceny', 'version' => 'wersja', 'reason' => 'powód zmiany'];
    }
}
