<?php

namespace App\Http\Requests\Admin;

use App\Models\Subject;
use App\Services\Authorization\RecordAccess;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

/**
 * GET filters. Invalid input redirects (303) to the same route without the
 * offending parameters, so a direct visit never loops (ERROR_HANDLING).
 */
class SubjectFilterRequest extends FormRequest
{
    public const PER_PAGE = [10, 25, 50];

    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && RecordAccess::admin($user) && $user->can('viewAny', Subject::class);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'archived' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'per_page' => ['nullable', 'integer', 'in:'.implode(',', self::PER_PAGE)],
            'sort' => ['nullable', 'in:name'],
            'direction' => ['nullable', 'in:asc,desc'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['q' => 'fraza wyszukiwania', 'archived' => 'filtr archiwum', 'page' => 'strona', 'per_page' => 'liczba na stronie', 'sort' => 'sortowanie', 'direction' => 'kierunek'];
    }

    /** @return array{q: string, archived: bool, page: int, perPage: int, sort: string, direction: 'asc'|'desc'} */
    public function filters(): array
    {
        $data = $this->validated();

        return [
            'q' => trim((string) ($data['q'] ?? '')),
            'archived' => (bool) ($data['archived'] ?? false),
            'page' => (int) ($data['page'] ?? 1),
            'perPage' => (int) ($data['per_page'] ?? 25),
            'sort' => 'name',
            'direction' => ($data['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        $valid = $this->except(array_keys($validator->errors()->messages()));
        $clean = array_intersect_key($valid, array_flip(array_keys($this->rules())));

        $exception = new ValidationException($validator);
        $exception->response = redirect()->to(route('admin.subjects.index', $clean), 303)
            ->withErrors($validator->errors());

        throw $exception;
    }
}
