<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\MutationRequest;
use App\Models\SchoolClass;
use App\Models\StudentEnrollment;

abstract class SchoolMutationRequest extends MutationRequest
{
    public function authorize(): bool
    {
        $class = $this->route('schoolClass');
        $enrollment = $this->route('enrollment');
        if ($enrollment instanceof StudentEnrollment) {
            abort_unless($class instanceof SchoolClass && $enrollment->school_class_id === $class->id, 404);
        }

        return $this->user()->can($this->isMethod('POST') ? 'create' : ($this->isMethod('DELETE') ? 'delete' : 'update'),
            $enrollment ?? ($this instanceof StoreEnrollmentRequest ? StudentEnrollment::class : ($class ?? SchoolClass::class)));
    }

    public function attributes(): array
    {
        return ['name' => 'nazwa klasy', 'academic_year_id' => 'rok szkolny', 'student_id' => 'uczeń', 'starts_on' => 'data rozpoczęcia', 'ends_on' => 'data zakończenia', 'transfer_from_enrollment_id' => 'poprzednie członkostwo', 'confirmed' => 'potwierdzenie'];
    }

    /** @return array<string, list<string>> */
    protected function dateRules(): array
    {
        return ['starts_on' => ['bail', 'required', 'date_format:Y-m-d'], 'ends_on' => ['bail', 'nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on']];
    }
}
