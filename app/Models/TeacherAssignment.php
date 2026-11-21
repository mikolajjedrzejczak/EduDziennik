<?php

namespace App\Models;

use App\Services\Authorization\RecordAccess;
use Carbon\CarbonImmutable;
use Database\Factories\TeacherAssignmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property CarbonImmutable|null $revoked_at */
class TeacherAssignment extends DomainModel
{
    /** @use HasFactory<TeacherAssignmentFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['revoked_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id')->withTrashed();
    }

    /** @return BelongsTo<SchoolClass, $this> */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'school_class_id')->withTrashed();
    }

    /** @return BelongsTo<Subject, $this> */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id')->withTrashed();
    }

    /** @return BelongsTo<Term, $this> */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'term_id');
    }

    /** @param Builder<TeacherAssignment> $query */
    public function scopeVisibleTo(Builder $query, User $actor): void
    {
        if (! RecordAccess::active($actor) || $actor->role === 'student') {
            $query->whereRaw('1 = 0');
        } elseif ($actor->role === 'teacher') {
            $query->active()->where('teacher_id', $actor->id);
        }
    }

    /**
     * Assignments covering the class/subject/term context of a gradebook column.
     *
     * @param  Builder<TeacherAssignment>  $query
     */
    public function scopeForAssessment(Builder $query, Assessment $assessment): void
    {
        $query->where([
            'school_class_id' => $assessment->school_class_id,
            'subject_id' => $assessment->subject_id,
            'term_id' => $assessment->term_id,
        ]);
    }

    /** @param Builder<TeacherAssignment> $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('revoked_at');
    }
}
