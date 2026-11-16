<?php

namespace App\Http\Requests;

use App\Models\Grade;
use App\Services\Authorization\RecordAccess;
use App\Services\Grades\StaffGradeList;
use App\Services\Grades\StudentGradeList;
use App\Support\ListFilters;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * GET grade filters use visible data for every role. Student owner selectors are prohibited;
 * staff selectors never widen the actor's record scope. F-203 adds teacher/admin lists.
 * Invalid input redirects (303) to the same route without the offending parameters.
 */
class GradeFilterRequest extends FormRequest
{
    public const PER_PAGE = [10, 25, 50];

    public const SORTS = ['graded_on', 'value', 'weight'];

    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && RecordAccess::active($user) && in_array($user->role, ['student', 'teacher', 'admin'], true) && $user->can('viewAny', Grade::class);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        if ($this->user()->role !== 'student') {
            $list = app(StaffGradeList::class);
            $actor = $this->user();

            return [
                ...ListFilters::rules(self::SORTS),
                'term_id' => ['nullable', 'integer', Rule::in($list->terms($actor)->pluck('id')->all())],
                'subject_id' => ['nullable', 'integer', Rule::in($list->ids($actor, 'subject_id'))],
                'category_id' => ['nullable', 'integer', Rule::in($list->ids($actor, 'category_id'))],
                'student_enrollment_id' => ['nullable', 'integer', Rule::in($list->ids($actor, 'student_enrollment_id'))],
                'student_id' => $actor->role === 'admin' ? ['nullable', 'integer', Rule::in($list->ids($actor, 'student_id'))] : ['prohibited'],
                'school_class_id' => $actor->role === 'admin' ? ['nullable', 'integer', Rule::in($list->ids($actor, 'school_class_id'))] : ['prohibited'],
                'date_from' => ['nullable', 'date_format:Y-m-d'],
                'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            ];
        }
        $list = app(StudentGradeList::class);
        $student = $this->user();

        return [
            ...ListFilters::rules(self::SORTS),
            'term_id' => ['bail', 'nullable', 'integer', Rule::in($list->terms($student)->pluck('id')->all())],
            'subject_id' => ['bail', 'nullable', 'integer', Rule::in($list->subjectIds($student))],
            'category_id' => ['bail', 'nullable', 'integer', Rule::in($list->categoryIds($student))],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            // A student never selects another owner.
            'student_id' => ['prohibited'],
            'student_enrollment_id' => ['prohibited'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'term_id' => 'semestr', 'subject_id' => 'przedmiot', 'category_id' => 'kategoria', 'q' => 'fraza wyszukiwania',
            'date_from' => 'data od', 'date_to' => 'data do', 'page' => 'strona', 'per_page' => 'liczba na stronie',
            'sort' => 'sortowanie', 'direction' => 'kierunek', 'student_id' => 'uczeń', 'student_enrollment_id' => 'zapis ucznia',
        ];
    }

    /**
     * @return array{termId: int|null, subjectId: int|null, categoryId: int|null, q: string, dateFrom: string|null, dateTo: string|null, page: int, perPage: int, sort: string, direction: 'asc'|'desc'}
     */
    public function filters(): array
    {
        $data = $this->validated();
        $id = fn (string $key): ?int => isset($data[$key]) ? (int) $data[$key] : null;

        return [
            'termId' => $id('term_id'),
            'subjectId' => $id('subject_id'),
            'categoryId' => $id('category_id'),
            'q' => trim((string) ($data['q'] ?? '')),
            'dateFrom' => $data['date_from'] ?? null,
            'dateTo' => $data['date_to'] ?? null,
            'page' => (int) ($data['page'] ?? 1),
            'perPage' => (int) ($data['per_page'] ?? 10),
            'sort' => $data['sort'] ?? 'graded_on',
            'direction' => ($data['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        $clean = array_intersect_key($this->except(array_keys($validator->errors()->messages())), array_flip(array_keys($this->rules())));

        $exception = new ValidationException($validator);
        $exception->response = redirect()->to(route($this->user()->role.'.grades.index', $clean), 303)->withErrors($validator->errors());

        throw $exception;
    }
}
