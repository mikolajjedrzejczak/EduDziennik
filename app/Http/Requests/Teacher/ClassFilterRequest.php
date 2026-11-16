<?php

namespace App\Http\Requests\Teacher;

use App\Models\TeacherAssignment;
use App\Services\Grades\TeacherClassList;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClassFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['teacher', 'admin'], true) && $this->user()->can('viewAny', TeacherAssignment::class);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'term_id' => ['bail', 'nullable', 'integer', Rule::in(app(TeacherClassList::class)->terms($this->user())->pluck('id')->all())],
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'per_page' => ['nullable', 'integer', 'in:10,25,50'],
            'sort' => ['nullable', 'in:name'], 'direction' => ['nullable', 'in:asc,desc'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['term_id' => 'semestr', 'q' => 'klasa lub przedmiot', 'page' => 'strona', 'per_page' => 'liczba na stronie', 'sort' => 'sortowanie', 'direction' => 'kierunek'];
    }

    protected function failedValidation(Validator $validator): void
    {
        $clean = array_intersect_key($this->except(array_keys($validator->errors()->messages())), $this->rules());
        $exception = new ValidationException($validator);
        $exception->response = redirect()->route($this->user()->role.'.gradebooks.index', $clean, 303)->withErrors($validator->errors());

        throw $exception;
    }
}
