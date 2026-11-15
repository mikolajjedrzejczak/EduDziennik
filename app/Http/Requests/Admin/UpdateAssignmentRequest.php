<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\MutationRequest;
use App\Models\TeacherAssignment;
use App\Services\Authorization\RecordAccess;

/** Only the revoked flag is accepted; the teacher/class/subject/term identity is immutable. */
class UpdateAssignmentRequest extends MutationRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $assignment = $this->route('assignment');

        return $user !== null && RecordAccess::admin($user) && $assignment instanceof TeacherAssignment && $user->can('update', $assignment);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['revoked' => ['required', 'boolean']];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['revoked' => 'status przydziału'];
    }
}
