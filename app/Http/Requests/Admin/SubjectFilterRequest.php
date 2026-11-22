<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\RedirectsInvalidFilters;
use App\Models\Subject;
use App\Services\Authorization\RecordAccess;
use App\Support\ListFilters;
use Illuminate\Foundation\Http\FormRequest;

/**
 * GET filters. Invalid input redirects (303) to the same route without the
 * offending parameters, so a direct visit never loops (ERROR_HANDLING).
 */
class SubjectFilterRequest extends FormRequest
{
    use RedirectsInvalidFilters;

    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && RecordAccess::admin($user) && $user->can('viewAny', Subject::class);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            ...ListFilters::rules(['name']),
            'archived' => ['nullable', 'boolean'],
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

    protected function filterRedirectUrl(array $query): string
    {
        return route('admin.subjects.index', $query);
    }
}
