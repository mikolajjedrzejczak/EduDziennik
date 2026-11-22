<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesGradeFields;
use App\Models\Grade;
use App\Services\Grades\GradeFormContext;
use Illuminate\Support\Facades\Gate;

class UpdateGradeRequest extends MutationRequest
{
    use ValidatesGradeFields;

    public function authorize(): bool
    {
        $grade = $this->grade();
        Gate::authorize('view', $grade);
        app(GradeFormContext::class)->resolve($this->user(), $this->input('assignment_id'), $grade->assessment_id, $grade->student_enrollment_id);
        Gate::authorize('update', $grade);

        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $grade = $this->grade();
        $term = $grade->assessment->term;
        $enrollment = $grade->enrollment;
        $start = max($term->starts_on->toDateString(), $enrollment->starts_on->toDateString());
        $end = min($term->ends_on->toDateString(), ($enrollment->ends_on ?? $enrollment->schoolClass->academicYear->ends_on)->toDateString(), now('Europe/Warsaw')->toDateString());

        return [
            'assignment_id' => ['required', 'integer'],
            'version' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            ...$this->gradeFieldRules($start, $end),
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [...$this->gradeFieldAttributes(), 'version' => 'wersja', 'reason' => 'powód zmiany'];
    }

    private function grade(): Grade
    {
        $grade = $this->route('grade');
        assert($grade instanceof Grade);

        return $grade;
    }
}
