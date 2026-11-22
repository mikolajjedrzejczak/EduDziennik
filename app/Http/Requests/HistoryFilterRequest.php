<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RedirectsInvalidFilters;
use App\Models\Grade;
use App\Models\GradeAudit;
use App\Models\StudentEnrollment;
use App\Services\Grades\GradeHistory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class HistoryFilterRequest extends FormRequest
{
    use RedirectsInvalidFilters;

    /** @var array<string, array<int, array{id: string, label: string}>>|null */
    private ?array $historyOptions = null;

    public function authorize(): bool
    {
        Gate::authorize('viewAny', GradeAudit::class);
        $grade = $this->route('grade');
        if ($grade instanceof Grade) {
            Gate::authorize('viewHistory', $grade);
        }
        // Explicit selectors receive the same 404 as a foreign record URL.
        foreach (['grade_id' => 'id', 'student_enrollment_id' => 'student_enrollment_id'] as $input => $column) {
            if ($input === 'student_enrollment_id' && $this->user()->role === 'student') {
                continue;
            }
            $value = $this->input($input);
            if (is_scalar($value) && ctype_digit((string) $value)) {
                if ($input === 'student_enrollment_id') {
                    abort_if($grade instanceof Grade && (int) $value !== $grade->student_enrollment_id, 404);
                    abort_unless(StudentEnrollment::visibleTo($this->user())->whereKey($value)->exists(), 404);

                    continue;
                }
                $query = Grade::withTrashed()->visibleTo($this->user())->where($column, $value);
                if ($grade instanceof Grade) {
                    $query->whereKey($grade->id);
                }
                abort_unless($query->exists(), 404);
            }
        }

        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $enrollmentId = $this->user()->role !== 'student' && is_scalar($this->input('student_enrollment_id')) && ctype_digit((string) $this->input('student_enrollment_id')) ? (int) $this->input('student_enrollment_id') : null;
        $options = fn (): array => $this->historyOptions ??= app(GradeHistory::class)->options($this->user(), $enrollmentId);

        return [
            'grade_id' => ['nullable', 'integer', 'min:1'],
            'student_enrollment_id' => $this->user()->role === 'student' ? ['prohibited'] : ['nullable', 'integer', 'min:1'],
            'student_id' => ['prohibited'],
            'term_id' => ['nullable', 'integer', $this->inWhenFilled('term_id', fn () => array_column($options()['terms'], 'id'))],
            'subject_id' => ['nullable', 'integer', $this->inWhenFilled('subject_id', fn () => array_column($options()['subjects'], 'id'))],
            'action' => ['nullable', Rule::in(['create', 'update', 'delete'])],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50])],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'grade_id' => 'ocena', 'student_enrollment_id' => 'zapis ucznia', 'student_id' => 'uczeń',
            'term_id' => 'semestr', 'subject_id' => 'przedmiot', 'action' => 'operacja',
            'date_from' => 'data od', 'date_to' => 'data do', 'page' => 'strona', 'per_page' => 'liczba na stronie',
        ];
    }

    /** @return array<string, mixed> */
    public function filters(): array
    {
        $data = $this->validated();
        $filters = [];
        foreach (['grade_id' => 'gradeId', 'student_enrollment_id' => 'studentEnrollmentId', 'term_id' => 'termId', 'subject_id' => 'subjectId', 'action' => 'action', 'date_from' => 'dateFrom', 'date_to' => 'dateTo'] as $key => $prop) {
            $filters[$prop] = isset($data[$key]) ? (string) $data[$key] : null;
        }

        return [...$filters, 'page' => (int) ($data['page'] ?? 1), 'perPage' => (int) ($data['per_page'] ?? 10)];
    }

    /** The detail URL is rebuilt without any query: these filters are never partially kept. */
    protected function filterRedirectUrl(array $query): string
    {
        return $this->url();
    }
}
