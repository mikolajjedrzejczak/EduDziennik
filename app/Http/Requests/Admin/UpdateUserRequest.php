<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\NormalizesAccountInput;
use App\Http\Requests\MutationRequest;
use App\Models\User;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends MutationRequest
{
    use NormalizesAccountInput;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('user')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'first_name' => self::NAME_RULES,
            'last_name' => self::NAME_RULES,
            'email' => ['bail', 'required', 'string', 'email', 'max:254', Rule::unique(User::class, 'email')->ignore($this->route('user'))],
            'role' => ['bail', 'required', Rule::in(User::ROLES)],
            'status' => ['bail', 'required', Rule::in(User::STATUSES)],
            'password' => ['bail', 'nullable', 'string', 'min:12', 'max:128', 'confirmed'],
            'enrollment' => ['prohibited'],
            'assignments' => ['prohibited'],
        ];
    }
}
