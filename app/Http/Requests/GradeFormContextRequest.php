<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesGradeFormContext;
use App\Models\Grade;
use App\Support\ListFilters;
use Illuminate\Foundation\Http\FormRequest;

class GradeFormContextRequest extends FormRequest
{
    use ResolvesGradeFormContext;

    public function authorize(): bool
    {
        $this->gradeFormContext();

        return $this->user()->can('createAny', Grade::class);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [...ListFilters::gradebookRules(), 'assignment_id' => ['required', 'integer'], 'assessment_id' => ['nullable', 'integer'], 'student_enrollment_id' => ['nullable', 'integer']];
    }
}
