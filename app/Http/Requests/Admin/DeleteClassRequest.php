<?php

namespace App\Http\Requests\Admin;

class DeleteClassRequest extends SchoolMutationRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['confirmed' => ['accepted']];
    }
}
