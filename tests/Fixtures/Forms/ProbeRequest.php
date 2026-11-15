<?php

namespace Tests\Fixtures\Forms;

use App\Http\Requests\MutationRequest;

/** Technical fixture only: no domain rules, models or database writes. */
class ProbeRequest extends MutationRequest
{
    public function authorize(): bool
    {
        // A deterministic stand-in for a scoped Policy, never an input/can prop.
        return $this->route('context') === 'allowed';
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'email' => ['bail', 'required', 'string', 'email', 'max:254'],
            'quantity' => ['bail', 'required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:500'],
            'password' => ['required', 'string', 'min:12', 'max:128', 'confirmed'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['quantity' => 'liczba', 'comment' => 'komentarz'];
    }
}
