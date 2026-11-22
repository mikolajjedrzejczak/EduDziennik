<?php

namespace App\Http\Requests\Teacher;

use App\Http\Requests\Concerns\RedirectsInvalidFilters;
use App\Models\TeacherAssignment;
use App\Support\ListFilters;
use Illuminate\Foundation\Http\FormRequest;

class GradebookFilterRequest extends FormRequest
{
    use RedirectsInvalidFilters;

    public function authorize(): bool
    {
        $assignment = $this->route('assignment');
        if ($assignment instanceof TeacherAssignment) {
            $this->user()->can('view', $assignment) || abort(404);

            return true;
        }

        return false;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ListFilters::gradebookRules();
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['q' => 'nazwisko ucznia', 'page' => 'strona uczniów', 'assessment_page' => 'strona kolumn', 'per_page' => 'liczba na stronie', 'sort' => 'sortowanie', 'direction' => 'kierunek'];
    }

    protected function filterRedirectUrl(array $query): string
    {
        return route($this->route()->getName(), [...$this->route()->parameters(), ...$query]);
    }
}
