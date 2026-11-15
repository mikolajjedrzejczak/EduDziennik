<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\MutationRequest;
use App\Models\Subject;
use App\Services\Authorization\RecordAccess;

/** Archiving (soft delete). Active-assignment protection is enforced by SubjectService under lock. */
class DeleteSubjectRequest extends MutationRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $subject = $this->route('subject');

        return $user !== null && RecordAccess::admin($user) && $subject instanceof Subject && $user->can('delete', $subject);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['confirmed' => ['required', 'accepted']];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['confirmed' => 'potwierdzenie archiwizacji'];
    }
}
