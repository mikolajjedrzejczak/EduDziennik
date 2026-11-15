<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\MutationRequest;
use App\Models\Subject;
use App\Services\Authorization\RecordAccess;
use Illuminate\Validation\Rule;

/** F-307: the name is unique across active and archived subjects (case-insensitive collation). */
class StoreSubjectRequest extends MutationRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && RecordAccess::admin($user) && $user->can('create', Subject::class);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['name' => ['bail', 'required', 'string', 'min:1', 'max:100', Rule::unique('subjects', 'name')]];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['name' => 'nazwa przedmiotu'];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['name.unique' => 'Przedmiot o tej nazwie już istnieje (także wśród zarchiwizowanych).'];
    }
}
