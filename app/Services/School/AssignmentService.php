<?php

namespace App\Services\School;

use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Models\Term;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Teacher scope (F-308). Lock order matches GradeService/WriteContext:
 * Term → teacher User → assignment row → class → subject, so a revoke and a grade write serialize.
 */
class AssignmentService
{
    public function assign(int $teacherId, int $classId, int $subjectId, int $termId): TeacherAssignment
    {
        return DB::transaction(function () use ($teacherId, $classId, $subjectId, $termId) {
            $term = Term::lockForUpdate()->find($termId);
            $teacher = User::withTrashed()->lockForUpdate()->find($teacherId);
            $existing = TeacherAssignment::where([
                'teacher_id' => $teacherId, 'school_class_id' => $classId, 'subject_id' => $subjectId, 'term_id' => $termId,
            ])->lockForUpdate()->first();
            $class = SchoolClass::lockForUpdate()->find($classId);
            $subject = Subject::lockForUpdate()->find($subjectId);
            $this->guard($teacher, $class, $subject, $term);

            if ($existing) {
                if ($existing->revoked_at !== null) {
                    $existing->update(['revoked_at' => null]);
                }

                return $existing;
            }

            return TeacherAssignment::create([
                'teacher_id' => $teacherId, 'school_class_id' => $classId, 'subject_id' => $subjectId, 'term_id' => $termId,
            ]);
        }, 3);
    }

    /** Sets revoked_at; idempotent. The row is never deleted, history stays attached. */
    public function revoke(int $assignmentId): TeacherAssignment
    {
        return DB::transaction(function () use ($assignmentId) {
            $reference = TeacherAssignment::findOrFail($assignmentId);
            Term::lockForUpdate()->findOrFail($reference->term_id);
            User::withTrashed()->lockForUpdate()->findOrFail($reference->teacher_id);
            $assignment = TeacherAssignment::lockForUpdate()->findOrFail($assignmentId);
            if ($assignment->revoked_at === null) {
                $assignment->update(['revoked_at' => now()]);
            }

            return $assignment;
        }, 3);
    }

    public function reactivate(int $assignmentId): TeacherAssignment
    {
        $reference = TeacherAssignment::findOrFail($assignmentId);

        return $this->assign($reference->teacher_id, $reference->school_class_id, $reference->subject_id, $reference->term_id);
    }

    private function guard(?User $teacher, ?SchoolClass $class, ?Subject $subject, ?Term $term): void
    {
        if (! $teacher || $teacher->trashed() || $teacher->role !== 'teacher' || $teacher->status !== 'active') {
            throw ValidationException::withMessages(['teacher_id' => 'Wybierz aktywnego nauczyciela.']);
        }
        if (! $class) {
            throw ValidationException::withMessages(['school_class_id' => 'Wybierz aktywną klasę.']);
        }
        if (! $subject) {
            throw ValidationException::withMessages(['subject_id' => 'Wybierz aktywny przedmiot.']);
        }
        if (! $term || $class->academic_year_id !== $term->academic_year_id) {
            throw ValidationException::withMessages(['term_id' => 'Semestr musi należeć do roku szkolnego klasy.']);
        }
        if ($term->closed_at !== null) {
            throw ValidationException::withMessages(['term_id' => 'Semestr jest zamknięty.']);
        }
    }
}
