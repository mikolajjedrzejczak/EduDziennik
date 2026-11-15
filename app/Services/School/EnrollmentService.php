<?php

namespace App\Services\School;

use App\Models\SchoolClass;
use App\Models\StudentEnrollment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class EnrollmentService
{
    // Operator/fixture callers omit actorId; HTTP callers always supply the session actor.
    public function enroll(int $studentId, int $classId, string $startsOn, ?string $endsOn = null, ?int $transferFromId = null, ?int $actorId = null): StudentEnrollment
    {
        $this->validateDates($startsOn, $endsOn);

        return DB::transaction(function () use ($studentId, $classId, $startsOn, $endsOn, $transferFromId, $actorId) {
            $student = $this->lockUsers($studentId, $actorId, 'create');
            $source = $transferFromId === null ? null : StudentEnrollment::where('student_id', $studentId)->findOrFail($transferFromId);
            $classIds = array_unique([$classId, ...($source ? [$source->school_class_id] : [])]);
            $classes = SchoolClass::withTrashed()->whereIn('id', $classIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $class = $classes->get($classId);
            abort_if($class === null || $class->trashed(), 404);
            if ($source !== null) {
                $source = StudentEnrollment::where('student_id', $studentId)->lockForUpdate()->findOrFail($transferFromId);
                $sourceEnd = $source->ends_on ?? $source->schoolClass->academicYear->ends_on;
                if ($source->school_class_id === $classId || $source->starts_on->toDateString() >= $startsOn || $sourceEnd->toDateString() < $startsOn) {
                    throw ValidationException::withMessages(['transfer_from_enrollment_id' => 'Wybierz członkostwo w innej klasie obejmujące dzień przeniesienia i rozpoczęte wcześniej.']);
                }
                $previousDay = CarbonImmutable::parse($startsOn)->subDay()->toDateString();
                $this->protectGrades($source, $source->starts_on->toDateString(), $previousDay);
                $source->update(['ends_on' => $previousDay]);
            }
            $this->validateMembership($student, $class, $startsOn, $endsOn);

            return StudentEnrollment::create(['student_id' => $studentId, 'school_class_id' => $classId, 'starts_on' => $startsOn, 'ends_on' => $endsOn]);
        }, 3);
    }

    public function update(int $actorId, int $classId, int $enrollmentId, string $startsOn, ?string $endsOn): StudentEnrollment
    {
        $this->validateDates($startsOn, $endsOn);
        $record = StudentEnrollment::where('school_class_id', $classId)->findOrFail($enrollmentId);

        return DB::transaction(function () use ($actorId, $classId, $enrollmentId, $startsOn, $endsOn, $record) {
            $student = $this->lockUsers($record->student_id, $actorId, 'update', $record);
            $class = SchoolClass::lockForUpdate()->findOrFail($classId);
            $enrollment = StudentEnrollment::where('school_class_id', $classId)->lockForUpdate()->findOrFail($enrollmentId);
            // Ending/correcting historical membership must also work for an inactive student.
            $this->validateMembership($student, $class, $startsOn, $endsOn, $enrollmentId);
            $this->protectGrades($enrollment, $startsOn, $endsOn ?? $class->academicYear->ends_on->toDateString());
            $enrollment->update(['starts_on' => $startsOn, 'ends_on' => $endsOn]);

            return $enrollment;
        }, 3);
    }

    private function lockUsers(int $studentId, ?int $actorId, string $ability, ?StudentEnrollment $record = null): User
    {
        $users = User::withTrashed()->whereIn('id', array_unique([$studentId, ...($actorId === null ? [] : [$actorId])]))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        if ($actorId !== null) {
            $actor = $users->get($actorId);
            abort_if($actor === null, 403);
            Gate::forUser($actor)->authorize($ability, $record ?? StudentEnrollment::class);
        }
        $student = $users->get($studentId);
        abort_if($student === null, 404);

        return $student;
    }

    private function validateDates(string $startsOn, ?string $endsOn): void
    {
        Validator::make(['starts_on' => $startsOn, 'ends_on' => $endsOn], [
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
        ])->validate();
    }

    private function validateMembership(User $student, SchoolClass $class, string $startsOn, ?string $endsOn, ?int $ignoreId = null): void
    {
        $year = $class->academicYear;
        $end = $endsOn ?? $year->ends_on->toDateString();
        if ($student->role !== 'student' || ($ignoreId === null && ($student->status !== 'active' || $student->trashed()))) {
            throw ValidationException::withMessages(['student_id' => 'Wybierz aktywne konto ucznia.']);
        }
        if ($startsOn < $year->starts_on->toDateString() || $startsOn > $year->ends_on->toDateString() || $end > $year->ends_on->toDateString()) {
            throw ValidationException::withMessages(['starts_on' => 'Daty członkostwa muszą mieścić się w roku szkolnym klasy.']);
        }
        // User lock serializes empty ranges too; an enrollment row lock alone would not.
        $overlap = StudentEnrollment::query()->join('school_classes', 'school_classes.id', '=', 'student_enrollments.school_class_id')
            ->join('academic_years', 'academic_years.id', '=', 'school_classes.academic_year_id')
            ->where('student_id', $student->id)->when($ignoreId !== null, fn ($q) => $q->where('student_enrollments.id', '!=', $ignoreId))
            ->where('student_enrollments.starts_on', '<=', $end)
            ->whereRaw('COALESCE(student_enrollments.ends_on, academic_years.ends_on) >= ?', [$startsOn])->exists();
        if ($overlap) {
            throw ValidationException::withMessages(['starts_on' => 'Członkostwa ucznia nie mogą się nakładać.']);
        }
    }

    private function protectGrades(StudentEnrollment $enrollment, string $startsOn, string $endsOn): void
    {
        if ($enrollment->grades()->withTrashed()->where(fn ($q) => $q->where('graded_on', '<', $startsOn)->orWhere('graded_on', '>', $endsOn))->exists()) {
            throw ValidationException::withMessages(['ends_on' => 'Zmiana dat pozostawiłaby istniejące oceny poza członkostwem.']);
        }
    }
}
