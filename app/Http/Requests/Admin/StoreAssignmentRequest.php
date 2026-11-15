<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\MutationRequest;
use App\Models\TeacherAssignment;
use App\Services\Authorization\RecordAccess;
use Illuminate\Validation\Rule;

/** Shape/existence only; teacher/class/subject/term consistency is re-checked by AssignmentService under lock. */
class StoreAssignmentRequest extends MutationRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && RecordAccess::admin($user) && $user->can('create', TeacherAssignment::class);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'teacher_id' => ['bail', 'required', 'integer', 'min:1', Rule::exists('users', 'id')->where('role', 'teacher')->where('status', 'active')->whereNull('deleted_at')],
            'school_class_id' => ['bail', 'required', 'integer', 'min:1', Rule::exists('school_classes', 'id')->whereNull('deleted_at')],
            'subject_id' => ['bail', 'required', 'integer', 'min:1', Rule::exists('subjects', 'id')->whereNull('deleted_at')],
            'term_id' => ['bail', 'required', 'integer', 'min:1', Rule::exists('terms', 'id')],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['teacher_id' => 'nauczyciel', 'school_class_id' => 'klasa', 'subject_id' => 'przedmiot', 'term_id' => 'semestr'];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['teacher_id.exists' => 'Wybierz aktywnego nauczyciela.', 'school_class_id.exists' => 'Wybierz aktywną klasę.', 'subject_id.exists' => 'Wybierz aktywny przedmiot.'];
    }
}
