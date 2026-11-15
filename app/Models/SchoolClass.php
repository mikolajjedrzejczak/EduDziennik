<?php

namespace App\Models;

use App\Services\Authorization\RecordAccess;
use Database\Factories\SchoolClassFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SchoolClass extends DomainModel
{
    /** @use HasFactory<SchoolClassFactory> */
    use HasFactory;

    use SoftDeletes;

    /** @param Builder<SchoolClass> $query */
    public function scopeVisibleTo(Builder $query, User $actor): void
    {
        if (! RecordAccess::active($actor)) {
            $query->whereRaw('1 = 0');
        } elseif ($actor->role === 'student') {
            $query->whereHas('enrollments', fn ($enrollments) => $enrollments->where('student_id', $actor->id));
        } elseif ($actor->role === 'teacher') {
            $query->whereHas('assignments', fn ($assignments) => $assignments->visibleTo($actor));
        }
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [];
    }

    /** @return BelongsTo<AcademicYear, $this> */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }

    /** @return HasMany<StudentEnrollment, $this> */
    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class, 'school_class_id');
    }

    /** @return HasMany<TeacherAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(TeacherAssignment::class, 'school_class_id');
    }

    /** @return HasMany<Assessment, $this> */
    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class, 'school_class_id');
    }
}
