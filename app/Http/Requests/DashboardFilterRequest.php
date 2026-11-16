<?php

namespace App\Http\Requests;

use App\Models\Grade;
use App\Services\Authorization\RecordAccess;
use App\Services\Grades\StudentGradeList;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DashboardFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        $student = $this->user();

        return $student !== null && RecordAccess::active($student) && $student->role === 'student' && $student->can('viewAny', Grade::class);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'term_id' => ['bail', 'nullable', 'integer', Rule::in(app(StudentGradeList::class)->terms($this->user())->pluck('id')->all())],
            'student_id' => ['prohibited'],
            'student_enrollment_id' => ['prohibited'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['term_id' => 'semestr', 'student_id' => 'uczeń', 'student_enrollment_id' => 'zapis ucznia'];
    }

    protected function failedValidation(Validator $validator): void
    {
        $clean = array_intersect_key($this->except(array_keys($validator->errors()->messages())), array_flip(array_keys($this->rules())));
        $exception = new ValidationException($validator);
        $exception->response = redirect()->to(route('student.dashboard', $clean), 303)->withErrors($validator->errors());

        throw $exception;
    }
}
