<?php

namespace App\Models;

use App\Services\Authorization\RecordAccess;
use Carbon\CarbonImmutable;
use Database\Factories\GradeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property CarbonImmutable $graded_on
 */
class Grade extends DomainModel
{
    /** @use HasFactory<GradeFactory> */
    use HasFactory;

    use SoftDeletes;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['value' => 'integer', 'weight' => 'integer', 'version' => 'integer', 'graded_on' => 'immutable_date'];
    }

    /** @return BelongsTo<StudentEnrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'student_enrollment_id');
    }

    /** @return BelongsTo<Assessment, $this> */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class, 'assessment_id')->withTrashed();
    }

    /** @return BelongsTo<GradeCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(GradeCategory::class, 'category_id');
    }

    /** @return BelongsTo<User, $this> */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by')->withTrashed();
    }

    /** @return HasMany<GradeAudit, $this> */
    public function audits(): HasMany
    {
        return $this->hasMany(GradeAudit::class, 'grade_id');
    }

    /** @param Builder<Grade> $query */
    public function scopeVisibleTo(Builder $query, User $actor): void
    {
        if (! RecordAccess::active($actor)) {
            $query->whereRaw('1 = 0');
        } elseif ($actor->role === 'student') {
            $query->whereHas('enrollment', fn ($q) => $q->where('student_id', $actor->id));
        } elseif ($actor->role === 'teacher') {
            $query->whereHas('assessment', fn ($q) => $q->whereExists(fn ($a) => $a->selectRaw('1')->from('teacher_assignments')
                ->whereColumn('teacher_assignments.school_class_id', 'assessments.school_class_id')
                ->whereColumn('teacher_assignments.subject_id', 'assessments.subject_id')
                ->whereColumn('teacher_assignments.term_id', 'assessments.term_id')
                ->where('teacher_id', $actor->id)->whereNull('revoked_at')));
        } elseif ($actor->role !== 'admin') {
            $query->whereRaw('1 = 0');
        }
    }
}
