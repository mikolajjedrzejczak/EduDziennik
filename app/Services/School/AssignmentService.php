<?php

namespace App\Services\School;

use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Models\Term;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignmentService
{
    public function assign(int $teacherId, int $classId, int $subjectId, int $termId): TeacherAssignment
    {
        return DB::transaction(function () use ($teacherId, $classId, $subjectId, $termId) {
            $term = Term::lockForUpdate()->findOrFail($termId);
            $teacher = User::lockForUpdate()->findOrFail($teacherId);
            $class = SchoolClass::lockForUpdate()->findOrFail($classId);
            Subject::lockForUpdate()->findOrFail($subjectId);
            if ($teacher->role !== 'teacher' || $teacher->status !== 'active' || $term->closed_at !== null || $class->academic_year_id !== $term->academic_year_id) {
                throw ValidationException::withMessages(['assignment' => 'Nieprawidłowy nauczyciel, rok klasy lub zamknięty semestr.']);
            }

            return TeacherAssignment::updateOrCreate([
                'teacher_id' => $teacherId, 'school_class_id' => $classId, 'subject_id' => $subjectId, 'term_id' => $termId,
            ], ['revoked_at' => null]);
        }, 3);
    }
}
