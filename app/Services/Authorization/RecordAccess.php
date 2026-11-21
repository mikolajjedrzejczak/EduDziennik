<?php

namespace App\Services\Authorization;

use App\Models\Assessment;
use App\Models\StudentEnrollment;
use App\Models\TeacherAssignment;
use App\Models\User;

final class RecordAccess
{
    public static function active(User $actor): bool
    {
        return $actor->exists && ! $actor->trashed() && $actor->status === 'active'
            && in_array($actor->role, User::ROLES, true);
    }

    public static function admin(User $actor): bool
    {
        return self::active($actor) && $actor->role === 'admin';
    }

    public static function writer(User $actor): bool
    {
        return self::active($actor) && in_array($actor->role, ['teacher', 'admin'], true);
    }

    public static function assessment(User $actor, Assessment $assessment): bool
    {
        return Assessment::withTrashed()->visibleTo($actor)->whereKey($assessment->id)->exists();
    }

    public static function assigned(User $actor, int $classId, int $subjectId, int $termId): bool
    {
        return self::admin($actor) || (self::active($actor) && $actor->role === 'teacher'
            && TeacherAssignment::active()->where([
                'teacher_id' => $actor->id, 'school_class_id' => $classId,
                'subject_id' => $subjectId, 'term_id' => $termId,
            ])->exists());
    }

    // UI/Policy preflight only. Services repeat these checks under transaction locks.
    public static function writable(Assessment $assessment, ?StudentEnrollment $enrollment = null, ?string $date = null): bool
    {
        $assessment = Assessment::withTrashed()->find($assessment->id);
        if (! $assessment || $assessment->trashed()) {
            return false;
        }
        if ($enrollment !== null) {
            $enrollment = StudentEnrollment::find($enrollment->id);
            if ($enrollment === null) {
                return false;
            }
        }

        return self::writableLoaded($assessment, $enrollment, $date);
    }

    /** Read preflight on a freshly loaded, authorized graph; never use for mutation authorization. */
    public static function writableLoaded(Assessment $assessment, ?StudentEnrollment $enrollment = null, ?string $date = null): bool
    {
        if ($assessment->trashed()) {
            return false;
        }
        $term = $assessment->term;
        $class = $assessment->schoolClass;
        $subject = $assessment->subject;
        $date ??= $assessment->graded_on->toDateString();
        if (! $term || ! $class || ! $subject || $term->closed_at !== null || $class->trashed() || $subject->trashed()
            || $class->academic_year_id !== $term->academic_year_id
            || $date < $term->starts_on->toDateString() || $date > $term->ends_on->toDateString()
            || $date > now('Europe/Warsaw')->toDateString()) {
            return false;
        }
        if ($enrollment === null) {
            return true;
        }
        $student = $enrollment->student;

        return $enrollment->school_class_id === $assessment->school_class_id
            && $student && self::active($student) && $student->role === 'student'
            && $date >= $enrollment->starts_on->toDateString()
            && $date <= ($enrollment->ends_on ?? $class->academicYear->ends_on)->toDateString();
    }
}
