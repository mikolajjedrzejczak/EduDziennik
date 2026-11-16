<?php

namespace App\Http\Requests\Teacher;

use App\Models\TeacherAssignment;
use App\Support\ListFilters;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class GradebookFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        $assignment = $this->route('assignment');
        if ($assignment instanceof TeacherAssignment) {
            $this->user()->can('view', $assignment) || abort(404);

            return true;
        }

        return false;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            ...ListFilters::rules(['last_name']),
            'assessment_page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['q' => 'nazwisko ucznia', 'page' => 'strona uczniów', 'assessment_page' => 'strona kolumn', 'per_page' => 'liczba na stronie', 'sort' => 'sortowanie', 'direction' => 'kierunek'];
    }

    protected function failedValidation(Validator $validator): void
    {
        $clean = array_intersect_key($this->except(array_keys($validator->errors()->messages())), $this->rules());
        $exception = new ValidationException($validator);
        $exception->response = redirect()->route($this->route()->getName(), [...$this->route()->parameters(), ...$clean], 303)->withErrors($validator->errors());

        throw $exception;
    }
}
