<?php

namespace App\Services\School;

use App\Models\SchoolClass;
use App\Models\StudentEnrollment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class EnrollmentService
{
    public function enroll(int $studentId, int $classId, string $startsOn, ?string $endsOn = null): StudentEnrollment
    {
        Validator::make(['starts_on' => $startsOn, 'ends_on' => $endsOn], [
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
        ])->validate();

        return DB::transaction(function () use ($studentId, $classId, $startsOn, $endsOn) {
            $student = User::lockForUpdate()->findOrFail($studentId);
            $class = SchoolClass::lockForUpdate()->findOrFail($classId);
            $year = $class->academicYear;
            if ($student->role !== 'student' || $student->status !== 'active' || $startsOn < $year->starts_on->toDateString() || ($endsOn ?? $year->ends_on->toDateString()) > $year->ends_on->toDateString() || $startsOn > $year->ends_on->toDateString()) {
                throw ValidationException::withMessages(['enrollment' => 'Nieprawidłowy uczeń lub daty członkostwa.']);
            }
            $end = $endsOn ?? $year->ends_on->toDateString();
            $overlap = StudentEnrollment::query()->join('school_classes', 'school_classes.id', '=', 'student_enrollments.school_class_id')
                ->join('academic_years', 'academic_years.id', '=', 'school_classes.academic_year_id')
                ->where('student_id', $studentId)->where('student_enrollments.starts_on', '<=', $end)
                ->whereRaw('COALESCE(student_enrollments.ends_on, academic_years.ends_on) >= ?', [$startsOn])->exists();
            if ($overlap) {
                throw ValidationException::withMessages(['enrollment' => 'Członkostwa ucznia nie mogą się nakładać.']);
            }

            return StudentEnrollment::create(['student_id' => $studentId, 'school_class_id' => $classId, 'starts_on' => $startsOn, 'ends_on' => $endsOn]);
        }, 3);
    }
}
