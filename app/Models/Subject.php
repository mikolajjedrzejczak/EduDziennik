<?php

namespace App\Models;

use App\Services\Authorization\RecordAccess;
use Database\Factories\SubjectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subject extends DomainModel
{
    /** @use HasFactory<SubjectFactory> */
    use HasFactory;

    use SoftDeletes;

    /** @param Builder<Subject> $query */
    public function scopeVisibleTo(Builder $query, User $actor): void
    {
        if (! RecordAccess::active($actor)) {
            $query->whereRaw('1 = 0');
        } elseif ($actor->role === 'student') {
            $query->whereHas('assessments', fn ($assessments) => $assessments->withTrashed()->visibleTo($actor));
        } elseif ($actor->role === 'teacher') {
            $query->whereHas('assignments', fn ($assignments) => $assignments->visibleTo($actor));
        }
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [];
    }

    /** @return HasMany<TeacherAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(TeacherAssignment::class, 'subject_id');
    }

    /** @return HasMany<Assessment, $this> */
    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class, 'subject_id');
    }
}
