<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\MutationRequest;
use App\Models\Subject;
use App\Services\Authorization\RecordAccess;
use Illuminate\Validation\Rule;

class UpdateSubjectRequest extends MutationRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $subject = $this->route('subject');

        return $user !== null && RecordAccess::admin($user) && $subject instanceof Subject && $user->can('update', $subject);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        /** @var Subject $subject */
        $subject = $this->route('subject');

        return ['name' => ['bail', 'required', 'string', 'min:1', 'max:100', Rule::unique('subjects', 'name')->ignore($subject->getKey())]];
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
