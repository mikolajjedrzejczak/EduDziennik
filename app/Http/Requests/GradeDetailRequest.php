<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RedirectsInvalidFilters;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class GradeDetailRequest extends FormRequest
{
    use RedirectsInvalidFilters;

    public function authorize(): bool
    {
        Gate::authorize('view', $this->route('grade'));

        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['history_page' => ['sometimes', 'integer', 'min:1', 'max:100000']];
    }

    /** The detail URL is rebuilt without any query: these filters are never partially kept. */
    protected function filterRedirectUrl(array $query): string
    {
        return $this->url();
    }
}
