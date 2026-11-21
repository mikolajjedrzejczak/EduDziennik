<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\NormalizesAccountInput;
use App\Http\Requests\MutationRequest;
use App\Models\User;
use App\Services\Authorization\RecordAccess;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Shape/existence only; class/term/date consistency is re-checked by Enrollment/AssignmentService inside the creation transaction. */
class StoreUserRequest extends MutationRequest
{
    use NormalizesAccountInput;

    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && RecordAccess::admin($user) && $user->can('create', User::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'first_name' => self::NAME_RULES,
            'last_name' => self::NAME_RULES,
            // Rule::unique does not filter soft-deleted rows: a deleted account keeps its address reserved.
            'email' => ['bail', 'required', 'string', 'email', 'max:254', Rule::unique(User::class, 'email')],
            'password' => ['bail', 'required', 'string', 'min:12', 'max:128', 'confirmed'],
            'role' => ['bail', 'required', Rule::in(User::ROLES)],
            'status' => ['bail', 'required', Rule::in(User::STATUSES)],
            'enrollment' => ['nullable', 'array:school_class_id,starts_on,ends_on', 'prohibited_unless:role,student', 'prohibited_unless:status,active'],
            'enrollment.school_class_id' => ['bail', 'required_with:enrollment', 'integer', 'min:1', Rule::exists('school_classes', 'id')->whereNull('deleted_at')],
            'enrollment.starts_on' => ['bail', 'required_with:enrollment', 'date_format:Y-m-d'],
            'enrollment.ends_on' => ['bail', 'nullable', 'date_format:Y-m-d', 'after_or_equal:enrollment.starts_on'],
            'assignments' => ['nullable', 'array', 'max:20', 'prohibited_unless:role,teacher', 'prohibited_unless:status,active'],
            'assignments.*' => ['array:school_class_id,subject_id,term_id'],
            'assignments.*.school_class_id' => ['bail', 'required', 'integer', 'min:1', Rule::exists('school_classes', 'id')->whereNull('deleted_at')],
            'assignments.*.subject_id' => ['bail', 'required', 'integer', 'min:1', Rule::exists('subjects', 'id')->whereNull('deleted_at')],
            'assignments.*.term_id' => ['bail', 'required', 'integer', 'min:1', Rule::exists('terms', 'id')],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $rows = $this->input('assignments');
            if (! is_array($rows)) {
                return;
            }
            $seen = [];
            foreach (array_values($rows) as $index => $row) {
                if (! is_array($row)) {
                    continue;
                }
                $key = implode('|', [$row['school_class_id'] ?? '', $row['subject_id'] ?? '', $row['term_id'] ?? '']);
                if (isset($seen[$key])) {
                    $validator->errors()->add("assignments.{$index}", 'Ten przydział został już dodany.');
                }
                $seen[$key] = true;
            }
        }];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'first_name' => 'imię', 'last_name' => 'nazwisko', 'email' => 'adres e-mail', 'password' => 'hasło', 'role' => 'rola', 'status' => 'status',
            'enrollment' => 'członkostwo w klasie', 'enrollment.school_class_id' => 'klasa', 'enrollment.starts_on' => 'data rozpoczęcia', 'enrollment.ends_on' => 'data zakończenia',
            'assignments' => 'przydziały', 'assignments.*.school_class_id' => 'klasa', 'assignments.*.subject_id' => 'przedmiot', 'assignments.*.term_id' => 'semestr',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'first_name.regex' => 'Imię może zawierać litery, spacje, apostrof i łącznik.',
            'last_name.regex' => 'Nazwisko może zawierać litery, spacje, apostrof i łącznik.',
            'email.unique' => 'Ten adres e-mail jest już zajęty.',
            'password.min' => 'Hasło musi mieć od 12 do 128 znaków.',
            'password.max' => 'Hasło musi mieć od 12 do 128 znaków.',
            'password.confirmed' => 'Potwierdzenie hasła nie zgadza się.',
            'enrollment.prohibited_unless' => 'Członkostwo w klasie dotyczy wyłącznie aktywnego konta ucznia.',
            'assignments.prohibited_unless' => 'Przydziały dotyczą wyłącznie aktywnego konta nauczyciela.',
            'assignments.max' => 'Można dodać najwyżej 20 przydziałów.',
        ];
    }
}
