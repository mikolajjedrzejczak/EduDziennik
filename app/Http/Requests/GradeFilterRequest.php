<?php

namespace App\Http\Requests;

use App\Models\Grade;
use App\Services\Authorization\RecordAccess;
use App\Services\Grades\StudentGradeList;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * GET filters of the grade list. F-102 implements the student scope: ids must belong to the
 * student's own visible data and owner selectors (student_id, student_enrollment_id) are
 * rejected. Teacher/admin scopes extend this request in F-203.
 * Invalid input redirects (303) to the same route without the offending parameters.
 */
class GradeFilterRequest extends FormRequest
{
    public const PER_PAGE = [10, 25, 50];

    public const SORTS = ['graded_on', 'value', 'weight'];

    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && RecordAccess::active($user) && $user->role === 'student' && $user->can('viewAny', Grade::class);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $list = app(StudentGradeList::class);
        $student = $this->user();

        return [
            'term_id' => ['bail', 'nullable', 'integer', Rule::in($list->terms($student)->pluck('id')->all())],
            'subject_id' => ['bail', 'nullable', 'integer', Rule::in($list->subjectIds($student))],
            'category_id' => ['bail', 'nullable', 'integer', Rule::in($list->categoryIds($student))],
            'q' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'per_page' => ['nullable', 'integer', Rule::in(self::PER_PAGE)],
            'sort' => ['nullable', Rule::in(self::SORTS)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
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
        $exception->response = redirect()->to(route('student.grades.index', $clean), 303)->withErrors($validator->errors());

        throw $exception;
    }
}
