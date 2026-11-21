<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\RedirectsInvalidFilters;
use App\Models\TeacherAssignment;
use App\Services\Authorization\RecordAccess;
use App\Support\ListFilters;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

/** GET filters (F-308). Invalid input redirects 303 to the same route without the offending parameters. */
class AssignmentFilterRequest extends FormRequest
{
    use RedirectsInvalidFilters;

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
            ...Arr::except(ListFilters::rules(['id']), 'q'),
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

    protected function filterRedirectUrl(array $query): string
    {
        return route('admin.assignments.index', $query);
    }
}
