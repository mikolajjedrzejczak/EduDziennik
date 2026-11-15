<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\MutationRequest;
use App\Models\TeacherAssignment;
use App\Services\Authorization\RecordAccess;

/** Revoke (sets revoked_at); never a physical delete. */
class DeleteAssignmentRequest extends MutationRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $assignment = $this->route('assignment');

        return $user !== null && RecordAccess::admin($user) && $assignment instanceof TeacherAssignment && $user->can('delete', $assignment);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['confirmed' => ['required', 'accepted']];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['confirmed' => 'potwierdzenie cofnięcia'];
    }
}
