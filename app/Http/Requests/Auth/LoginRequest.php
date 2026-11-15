<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\MutationRequest;

class LoginRequest extends MutationRequest
{
    public function authorize(): bool
    {
        return true; // The guest middleware owns access to this public form.
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['email' => ['required', 'string', 'email', 'max:254'], 'password' => ['required', 'string']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'email.required' => 'Wpisz adres e-mail.',
            'email.email' => 'Wpisz pełny adres, np. jan.kowalski@lo4.edu.pl.',
            'password.required' => 'Wpisz hasło.',
        ];
    }
}
