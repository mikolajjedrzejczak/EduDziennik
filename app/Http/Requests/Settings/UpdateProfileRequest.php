<?php

namespace App\Http\Requests\Settings;

use App\Http\Requests\Concerns\NormalizesAccountInput;
use App\Http\Requests\MutationRequest;

class UpdateProfileRequest extends MutationRequest
{
    use NormalizesAccountInput;

    public function authorize(): bool
    {
        return $this->user()?->can('updateProfile', $this->user()) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['first_name' => self::NAME_RULES, 'last_name' => self::NAME_RULES];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['first_name' => 'imię', 'last_name' => 'nazwisko'];
    }
}
