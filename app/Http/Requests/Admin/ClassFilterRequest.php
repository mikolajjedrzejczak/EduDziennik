<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\RedirectsInvalidFilters;
use App\Models\SchoolClass;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClassFilterRequest extends FormRequest
{
    use RedirectsInvalidFilters;

    public function authorize(): bool
    {
        return $this->user()->can('create', SchoolClass::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'academic_year_id' => ['bail', 'nullable', 'integer', 'min:1', Rule::exists('academic_years', 'id')],
            'term_id' => ['bail', 'nullable', 'integer', 'min:1', Rule::exists('terms', 'id')],
            'selected_class_id' => ['bail', 'nullable', 'integer', 'min:1'],
            'archived' => ['nullable', 'boolean'], 'q' => ['nullable', 'string', 'max:100'],
            'page' => ['bail', 'nullable', 'integer', 'min:1'], 'enrollment_page' => ['bail', 'nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', Rule::in([10, 25, 50])], 'sort' => ['nullable', Rule::in(['name'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['q' => 'wyszukiwanie', 'academic_year_id' => 'rok szkolny', 'term_id' => 'semestr', 'selected_class_id' => 'wybrana klasa', 'archived' => 'archiwalne', 'page' => 'strona klas', 'enrollment_page' => 'strona członkostw', 'per_page' => 'liczba rekordów na stronie', 'sort' => 'sortowanie', 'direction' => 'kierunek sortowania'];
    }

    protected function filterRedirectUrl(array $query): string
    {
        return route('admin.classes.index', $query);
    }
}
