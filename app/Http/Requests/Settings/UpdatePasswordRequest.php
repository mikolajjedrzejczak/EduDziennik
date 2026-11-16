<?php

namespace App\Http\Requests\Settings;

use App\Http\Requests\MutationRequest;

class UpdatePasswordRequest extends MutationRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('updateProfile', $this->user()) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'current_password' => ['bail', 'required', 'string', 'current_password'],
            'password' => ['bail', 'required', 'string', 'min:12', 'max:128', 'confirmed', 'different:current_password'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['current_password' => 'obecne hasło', 'password' => 'nowe hasło'];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'current_password.current_password' => 'Obecne hasło jest nieprawidłowe.',
            'password.different' => 'Nowe hasło musi być inne niż obecne.',
            'password.confirmed' => 'Potwierdzenie hasła nie zgadza się.',
        ];
    }
}
