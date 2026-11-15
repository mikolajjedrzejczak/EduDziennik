<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class StoreEnrollmentRequest extends SchoolMutationRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [...$this->dateRules(), 'student_id' => ['bail', 'required', 'integer', 'min:1', Rule::exists('users', 'id')->where('role', 'student')->where('status', 'active')->whereNull('deleted_at')], 'transfer_from_enrollment_id' => ['bail', 'nullable', 'integer', 'min:1', Rule::exists('student_enrollments', 'id')->where('student_id', $this->input('student_id'))]];
    }
}
