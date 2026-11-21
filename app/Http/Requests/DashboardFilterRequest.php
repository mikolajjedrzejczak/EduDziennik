<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RedirectsInvalidFilters;
use App\Models\Grade;
use App\Models\User;
use App\Services\Authorization\RecordAccess;
use App\Services\Grades\StudentGradeList;
use App\Services\Grades\TeacherClassList;
use App\Services\Statistics\AdminDashboard;
use Illuminate\Foundation\Http\FormRequest;

class DashboardFilterRequest extends FormRequest
{
    use RedirectsInvalidFilters;

    public function authorize(): bool
    {
        $actor = $this->user();

        return $actor !== null && RecordAccess::active($actor) && match ($actor->role) {
            'admin' => $actor->can('viewAny', User::class),
            'student', 'teacher' => $actor->can('viewAny', Grade::class),
            default => false,
        };
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'term_id' => ['bail', 'nullable', 'integer', $this->inWhenFilled('term_id', fn () => app(match ($this->user()->role) {
                'admin' => AdminDashboard::class,
                'teacher' => TeacherClassList::class,
                default => StudentGradeList::class,
            })->terms($this->user())->pluck('id')->all())],
            'student_id' => ['prohibited'],
            'student_enrollment_id' => ['prohibited'],
        ];
    }

    public function termId(): ?int
    {
        $termId = $this->validated('term_id');

        return $termId === null ? null : (int) $termId;
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['term_id' => 'semestr', 'student_id' => 'uczeń', 'student_enrollment_id' => 'zapis ucznia'];
    }

    protected function filterRedirectUrl(array $query): string
    {
        return route($this->user()->role.'.dashboard', $query);
    }
}
