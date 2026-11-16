<?php

namespace App\Http\Requests;

use App\Models\Grade;
use App\Models\User;
use App\Services\Authorization\RecordAccess;
use App\Services\Grades\StudentGradeList;
use App\Services\Grades\TeacherClassList;
use App\Services\Statistics\AdminDashboard;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DashboardFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();

        return $actor !== null && RecordAccess::active($actor) && match ($actor->role) {
            'admin' => $actor->can('viewAny', User::class),
            'student', 'teacher' => $actor->can('viewAny', Grade::class),
            default => false,
        };
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'term_id' => ['bail', 'nullable', 'integer', Rule::in(app(match ($this->user()->role) {
                'admin' => AdminDashboard::class,
                'teacher' => TeacherClassList::class,
                default => StudentGradeList::class,
            })->terms($this->user())->pluck('id')->all())],
            'student_id' => ['prohibited'],
            'student_enrollment_id' => ['prohibited'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['term_id' => 'semestr', 'student_id' => 'uczeń', 'student_enrollment_id' => 'zapis ucznia'];
    }

    protected function failedValidation(Validator $validator): void
    {
        $clean = array_intersect_key($this->except(array_keys($validator->errors()->messages())), array_flip(array_keys($this->rules())));
        $exception = new ValidationException($validator);
        $exception->response = redirect()->to(route($this->user()->role.'.dashboard', $clean), 303)->withErrors($validator->errors());

        throw $exception;
    }
}
