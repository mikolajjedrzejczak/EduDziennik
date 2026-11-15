<?php

namespace App\Services\School;

use App\Models\Subject;
use App\Models\TeacherAssignment;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Subject dictionary (F-307): create, rename, archive. History keeps the archived row (soft delete, FK RESTRICT). */
class SubjectService
{
    private const DUPLICATE = 'Przedmiot o tej nazwie już istnieje (także wśród zarchiwizowanych).';

    public function create(string $name): Subject
    {
        try {
            return DB::transaction(fn () => Subject::create(['name' => $name]), 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => self::DUPLICATE]);
        }
    }

    public function rename(int $subjectId, string $name): Subject
    {
        try {
            return DB::transaction(function () use ($subjectId, $name) {
                $subject = Subject::lockForUpdate()->findOrFail($subjectId);
                $subject->update(['name' => $name]);

                return $subject;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => self::DUPLICATE]);
        }
    }

    public function archive(int $subjectId): void
    {
        DB::transaction(function () use ($subjectId) {
            $subject = Subject::lockForUpdate()->findOrFail($subjectId);
            if (TeacherAssignment::where('subject_id', $subject->id)->whereNull('revoked_at')->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['form' => 'Nie można zarchiwizować przedmiotu z aktywnymi przydziałami nauczycieli. Najpierw cofnij przydziały.']);
            }
            $subject->delete();
        }, 3);
    }
}
