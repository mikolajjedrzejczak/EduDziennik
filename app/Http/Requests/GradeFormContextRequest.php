<?php

namespace App\Http\Requests;

use App\Models\Grade;
use App\Services\Grades\GradeFormContext;
use Illuminate\Foundation\Http\FormRequest;

class GradeFormContextRequest extends FormRequest
{
    public function authorize(): bool
    {
        app(GradeFormContext::class)->resolve($this->user(), $this->input('assignment_id'), $this->input('assessment_id'), $this->input('student_enrollment_id'));

        return $this->user()->can('createAny', Grade::class);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['assignment_id' => ['required', 'integer'], 'assessment_id' => ['nullable', 'integer'], 'student_enrollment_id' => ['nullable', 'integer']];
    }
}
