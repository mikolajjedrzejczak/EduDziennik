<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class StoreClassRequest extends SchoolMutationRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['academic_year_id' => ['bail', 'required', 'integer', 'min:1', Rule::exists('academic_years', 'id')], 'name' => ['bail', 'required', 'string', 'max:20', 'regex:/^[A-Za-z0-9][A-Za-z0-9 -]{0,19}$/', Rule::unique('school_classes', 'name')->where('academic_year_id', $this->input('academic_year_id'))]];
    }
}
