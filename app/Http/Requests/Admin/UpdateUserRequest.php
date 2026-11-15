<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\MutationRequest;
use App\Models\User;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends MutationRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('user')) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $trim = fn (mixed $value) => is_string($value) ? trim($value) : $value;
        $this->merge([
            'first_name' => $trim($this->input('first_name')),
            'last_name' => $trim($this->input('last_name')),
            'email' => is_string($this->input('email')) ? mb_strtolower(trim($this->input('email'))) : $this->input('email'),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $name = ['bail', 'required', 'string', 'max:80', "regex:/^(?=.*\p{L})[\p{L}\p{M} '’-]+$/u"];

        return [
            'first_name' => $name,
            'last_name' => $name,
            'email' => ['bail', 'required', 'string', 'email', 'max:254', Rule::unique(User::class, 'email')->ignore($this->route('user'))],
            'role' => ['bail', 'required', Rule::in(['student', 'teacher', 'admin'])],
            'status' => ['bail', 'required', Rule::in(['active', 'inactive'])],
            'password' => ['bail', 'nullable', 'string', 'min:12', 'max:128', 'confirmed'],
            'enrollment' => ['prohibited'],
            'assignments' => ['prohibited'],
        ];
    }
}
