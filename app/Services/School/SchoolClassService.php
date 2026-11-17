<?php

namespace App\Services\School;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SchoolClassService
{
    public function create(int $actorId, int $yearId, string $name): SchoolClass
    {
        return DB::transaction(function () use ($actorId, $yearId, $name) {
            Gate::forUser(User::lockForUpdate()->findOrFail($actorId))->authorize('create', SchoolClass::class);
            // Also serializes concurrent inserts into the same year's namespace.
            AcademicYear::lockForUpdate()->findOrFail($yearId);
            $this->uniqueName($yearId, $name);

            return SchoolClass::create(['academic_year_id' => $yearId, 'name' => $name]);
        }, 3);
    }

    public function rename(int $actorId, int $classId, string $name): void
    {
        DB::transaction(function () use ($actorId, $classId, $name) {
            $actor = User::lockForUpdate()->findOrFail($actorId);
            $record = SchoolClass::findOrFail($classId);
            AcademicYear::lockForUpdate()->findOrFail($record->academic_year_id);
            $class = SchoolClass::lockForUpdate()->findOrFail($classId);
            Gate::forUser($actor)->authorize('update', $class);
            $this->uniqueName($class->academic_year_id, $name, $classId);
            $class->update(['name' => $name]);
        }, 3);
    }

    public function archive(int $actorId, int $classId): void
    {
        DB::transaction(function () use ($actorId, $classId) {
            $actor = User::lockForUpdate()->findOrFail($actorId);
            $class = SchoolClass::lockForUpdate()->findOrFail($classId);
            Gate::forUser($actor)->authorize('delete', $class);
            if ($class->enrollments()->endingOnOrAfter(now('Europe/Warsaw')->toDateString())->exists()
                || $class->assignments()->whereNull('revoked_at')->exists()) {
                throw ValidationException::withMessages(['form' => 'Najpierw zakończ członkostwa uczniów i cofnij przydziały nauczycieli.']);
            }
            $class->delete();
        }, 3);
    }

    private function uniqueName(int $yearId, string $name, ?int $ignoreId = null): void
    {
        if (SchoolClass::withTrashed()->where('academic_year_id', $yearId)->where('name', $name)->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))->lockForUpdate()->first(['id']) !== null) {
            throw ValidationException::withMessages(['name' => 'Nazwa klasy w tym roku szkolnym jest już zajęta, również w archiwum.']);
        }
    }
}
