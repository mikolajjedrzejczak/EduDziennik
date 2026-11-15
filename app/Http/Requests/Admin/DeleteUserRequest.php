<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\MutationRequest;
use App\Models\User;
use Illuminate\Validation\Rule;

class DeleteUserRequest extends MutationRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('delete', $this->route('user')) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $value = $this->input('confirmation_last_name');
        $this->merge(['confirmation_last_name' => is_string($value) ? trim($value) : $value]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $user = $this->route('user');
        assert($user instanceof User);

        return ['confirmation_last_name' => ['bail', 'required', 'string', 'max:80', Rule::in([$user->last_name])]];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['confirmation_last_name.in' => 'Wpisz aktualne nazwisko użytkownika, aby potwierdzić usunięcie.'];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['confirmation_last_name' => 'nazwisko potwierdzenia'];
    }
}
