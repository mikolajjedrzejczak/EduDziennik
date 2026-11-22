<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\RedirectsInvalidFilters;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\Authorization\RecordAccess;
use App\Support\ListFilters;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * GET filters of the admin user list (F-302). Invalid input redirects (303) to
 * the same route without the offending parameters (ERROR_HANDLING).
 */
class UserFilterRequest extends FormRequest
{
    use RedirectsInvalidFilters;

    public const SORTS = ['last_name', 'email', 'role', 'created_at'];

    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && RecordAccess::admin($user) && $user->can('viewAny', User::class);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            ...ListFilters::rules(self::SORTS),
            'role' => ['nullable', Rule::in(User::ROLES)],
            'status' => ['nullable', Rule::in([...User::STATUSES, 'deleted'])],
            'school_class_id' => ['bail', 'nullable', 'integer', 'min:1', 'required_with:term_id', Rule::exists('school_classes', 'id')],
            'term_id' => ['bail', 'nullable', 'integer', 'min:1', 'required_with:school_class_id', Rule::exists('terms', 'id')->where(
                fn ($terms) => $terms->whereIn('academic_year_id', SchoolClass::withTrashed()->whereKey((int) $this->input('school_class_id'))->select('academic_year_id')),
            )],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'q' => 'fraza wyszukiwania', 'role' => 'rola', 'status' => 'status', 'school_class_id' => 'klasa',
            'term_id' => 'semestr', 'page' => 'strona', 'per_page' => 'liczba na stronie',
            'sort' => 'sortowanie', 'direction' => 'kierunek',
        ];
    }

    /**
     * @return array{q: string, role: ?string, status: ?string, schoolClassId: ?int, termId: ?int, page: int, perPage: int, sort: string, direction: 'asc'|'desc'}
     */
    public function filters(): array
    {
        $data = $this->validated();
        $q = trim((string) ($data['q'] ?? ''));

        return [
            'q' => $q,
            'role' => $data['role'] ?? null,
            'status' => $data['status'] ?? null,
            'schoolClassId' => isset($data['school_class_id']) ? (int) $data['school_class_id'] : null,
            'termId' => isset($data['term_id']) ? (int) $data['term_id'] : null,
            'page' => (int) ($data['page'] ?? 1),
            'perPage' => (int) ($data['per_page'] ?? 10),
            'sort' => $data['sort'] ?? 'last_name',
            'direction' => ($data['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc',
        ];
    }

    /**
     * Class and term only make sense together, so a rejected one drops its partner.
     *
     * @return list<string>
     */
    protected function rejectedFilters(Validator $validator): array
    {
        $invalid = array_keys($validator->errors()->messages());

        return array_intersect(['school_class_id', 'term_id'], $invalid) === [] ? $invalid : [...$invalid, 'school_class_id', 'term_id'];
    }

    protected function filterRedirectUrl(array $query): string
    {
        return route('admin.users.index', $query);
    }
}
