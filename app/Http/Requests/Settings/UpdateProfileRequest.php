<?php

namespace App\Http\Requests\Settings;

use App\Http\Requests\MutationRequest;

class UpdateProfileRequest extends MutationRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('updateProfile', $this->user()) ?? false;
    }

    protected function prepareForValidation(): void
    {
        foreach (['first_name', 'last_name'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field))]);
            }
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $name = ['bail', 'required', 'string', 'max:80', "regex:/^(?=.*\\p{L})[\\p{L}\\p{M} '’-]+$/u"];

        return ['first_name' => $name, 'last_name' => $name];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['first_name' => 'imię', 'last_name' => 'nazwisko'];
    }
}
