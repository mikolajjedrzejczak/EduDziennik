<?php

namespace App\Http\Requests\Admin;

use App\Models\TeacherAssignment;
use App\Services\Authorization\RecordAccess;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

/** GET filters (F-308). Invalid input redirects 303 to the same route without the offending parameters. */
class AssignmentFilterRequest extends FormRequest
{
    public const PER_PAGE = [10, 25, 50];

    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && RecordAccess::admin($user) && $user->can('create', TeacherAssignment::class);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'teacher_id' => ['nullable', 'integer', 'min:1'],
            'school_class_id' => ['nullable', 'integer', 'min:1'],
            'subject_id' => ['nullable', 'integer', 'min:1'],
            'term_id' => ['nullable', 'integer', 'min:1'],
            'revoked' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'per_page' => ['nullable', 'integer', 'in:'.implode(',', self::PER_PAGE)],
            'sort' => ['nullable', 'in:id'],
            'direction' => ['nullable', 'in:asc,desc'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['teacher_id' => 'nauczyciel', 'school_class_id' => 'klasa', 'subject_id' => 'przedmiot', 'term_id' => 'semestr', 'revoked' => 'filtr cofniętych', 'page' => 'strona', 'per_page' => 'liczba na stronie', 'sort' => 'sortowanie', 'direction' => 'kierunek'];
    }

    /** @return array{teacherId: ?int, schoolClassId: ?int, subjectId: ?int, termId: ?int, revoked: bool, page: int, perPage: int, sort: string, direction: 'asc'|'desc'} */
    public function filters(): array
    {
        $data = $this->validated();
        $id = fn (string $key): ?int => isset($data[$key]) ? (int) $data[$key] : null;

        return [
            'teacherId' => $id('teacher_id'), 'schoolClassId' => $id('school_class_id'), 'subjectId' => $id('subject_id'), 'termId' => $id('term_id'),
            'revoked' => (bool) ($data['revoked'] ?? false),
            'page' => (int) ($data['page'] ?? 1), 'perPage' => (int) ($data['per_page'] ?? 10),
            'sort' => 'id', 'direction' => ($data['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        $valid = $this->except(array_keys($validator->errors()->messages()));
        $clean = array_intersect_key($valid, array_flip(array_keys($this->rules())));

        $exception = new ValidationException($validator);
        $exception->response = redirect()->to(route('admin.assignments.index', $clean), 303)->withErrors($validator->errors());

        throw $exception;
    }
}
