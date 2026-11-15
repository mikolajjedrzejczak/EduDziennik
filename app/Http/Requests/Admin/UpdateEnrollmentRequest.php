<?php

namespace App\Http\Requests\Admin;

class UpdateEnrollmentRequest extends SchoolMutationRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->dateRules();
    }
}
