<?php

namespace App\Services\Grades;

use App\Models\GradeCategory;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Models\Term;
use App\Models\User;
use App\Services\Authorization\RecordAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class WriteContext
{
    // The caller owns a transaction and has locked Term, then users ordered by ID.
    public function authorize(User $actor, Term $term, int $classId, int $subjectId): void
    {
        if (! RecordAccess::writer($actor)) {
            throw new AuthorizationException('Brak uprawnień do zapisu ocen.');
        }
        if ($actor->role === 'teacher' && ! TeacherAssignment::active()->where([
            'teacher_id' => $actor->id, 'school_class_id' => $classId, 'subject_id' => $subjectId, 'term_id' => $term->id,
        ])->lockForUpdate()->first()) {
            throw new AuthorizationException('Brak uprawnień do zapisu ocen.');
        }
        $class = SchoolClass::lockForUpdate()->findOrFail($classId);
        Subject::lockForUpdate()->findOrFail($subjectId);
        if ($term->closed_at !== null || $class->academic_year_id !== $term->academic_year_id) {
            throw ValidationException::withMessages(['term' => 'Semestr jest zamknięty lub niezgodny z rokiem klasy.']);
        }
    }

    public function dateAndCategory(Term $term, string $date, int $categoryId): void
    {
        if ($date < $term->starts_on->toDateString() || $date > $term->ends_on->toDateString() || $date > now('Europe/Warsaw')->toDateString()) {
            throw ValidationException::withMessages(['graded_on' => 'Data oceny musi mieścić się w semestrze i nie może być w przyszłości.']);
        }
        if (! GradeCategory::whereKey($categoryId)->where('is_active', true)->lockForUpdate()->first()) {
            throw ValidationException::withMessages(['category_id' => 'Kategoria musi być aktywna.']);
        }
    }
}
