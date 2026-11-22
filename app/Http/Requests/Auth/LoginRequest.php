<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Concerns\NormalizesAccountInput;
use App\Http\Requests\MutationRequest;

class LoginRequest extends MutationRequest
{
    use NormalizesAccountInput;

    public function authorize(): bool
    {
        return true; // The guest middleware owns access to this public form.
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
