<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class GradeDetailRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('view', $this->route('grade'));

        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['history_page' => ['sometimes', 'integer', 'min:1', 'max:100000']];
    }

    protected function failedValidation(Validator $validator): void
    {
        $exception = new ValidationException($validator);
        $exception->response = redirect()->to($this->url(), 303)->withErrors($validator->errors());

        throw $exception;
    }
}
