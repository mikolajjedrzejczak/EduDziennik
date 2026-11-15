<?php

namespace App\Http\Requests\Admin;

use App\Models\SchoolClass;
use Illuminate\Validation\Rule;

class UpdateClassRequest extends SchoolMutationRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $class = $this->route('schoolClass');
        assert($class instanceof SchoolClass);

        return ['name' => ['bail', 'required', 'string', 'max:20', 'regex:/^[A-Za-z0-9][A-Za-z0-9 -]{0,19}$/', Rule::unique('school_classes', 'name')->where('academic_year_id', $class->academic_year_id)->ignore($class->id)]];
    }
}
