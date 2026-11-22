<?php

namespace App\Http\Requests\Teacher;

use App\Http\Requests\Concerns\RedirectsInvalidFilters;
use App\Models\TeacherAssignment;
use App\Services\Grades\TeacherClassList;
use App\Support\ListFilters;
use Illuminate\Foundation\Http\FormRequest;

class ClassFilterRequest extends FormRequest
{
    use RedirectsInvalidFilters;

    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['teacher', 'admin'], true) && $this->user()->can('viewAny', TeacherAssignment::class);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'term_id' => ['bail', 'nullable', 'integer', $this->inWhenFilled('term_id', fn () => app(TeacherClassList::class)->terms($this->user())->pluck('id')->all())],
            ...ListFilters::rules(['name']),
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['term_id' => 'semestr', 'q' => 'klasa lub przedmiot', 'page' => 'strona', 'per_page' => 'liczba na stronie', 'sort' => 'sortowanie', 'direction' => 'kierunek'];
    }

    protected function filterRedirectUrl(array $query): string
    {
        return route($this->user()->role.'.gradebooks.index', $query);
    }
}
