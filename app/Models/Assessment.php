<?php

namespace App\Models;

use App\Services\Authorization\RecordAccess;
use Carbon\CarbonImmutable;
use Database\Factories\AssessmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property CarbonImmutable $graded_on
 */
class Assessment extends DomainModel
{
    /** @use HasFactory<AssessmentFactory> */
    use HasFactory;

    use SoftDeletes;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['default_weight' => 'integer', 'graded_on' => 'immutable_date'];
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

    /** @return BelongsTo<GradeCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(GradeCategory::class, 'category_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /** @return HasMany<Grade, $this> */
    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class, 'assessment_id');
    }

    /** @param Builder<Assessment> $query */
    public function scopeVisibleTo(Builder $query, User $actor): void
    {
        if (! RecordAccess::active($actor)) {
            $query->whereRaw('1 = 0');
        } elseif ($actor->role === 'student') {
            $query->whereHas('grades', fn ($grades) => $grades->withTrashed()->visibleTo($actor));
        } elseif ($actor->role === 'teacher') {
            $query->whereExists(fn ($assignments) => $assignments->selectRaw('1')->from('teacher_assignments')
                ->whereColumn('teacher_assignments.school_class_id', 'assessments.school_class_id')
                ->whereColumn('teacher_assignments.subject_id', 'assessments.subject_id')
                ->whereColumn('teacher_assignments.term_id', 'assessments.term_id')
                ->where('teacher_assignments.teacher_id', $actor->id)->whereNull('teacher_assignments.revoked_at'));
        }
    }

    protected static function booted(): void
    {
        static::updating(function (Assessment $assessment) {
            if ($assessment->isDirty(['school_class_id', 'subject_id', 'term_id', 'category_id', 'topic', 'default_weight', 'graded_on', 'created_by'])) {
                throw new \LogicException('Metadane kolumny są niezmienne.');
            }
        });
    }
}
