<?php

namespace App\Models;

use App\Services\Authorization\RecordAccess;
use Carbon\CarbonImmutable;
use Database\Factories\StudentEnrollmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable|null $ends_on
 */
class StudentEnrollment extends DomainModel
{
    /** @use HasFactory<StudentEnrollmentFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['starts_on' => 'immutable_date', 'ends_on' => 'immutable_date'];
    }

    /** @return BelongsTo<User, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id')->withTrashed();
    }

    /** @return BelongsTo<SchoolClass, $this> */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'school_class_id')->withTrashed();
    }

    /** @return HasMany<Grade, $this> */
    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class, 'student_enrollment_id');
    }

    /** @param Builder<StudentEnrollment> $query */
    public function scopeVisibleTo(Builder $query, User $actor): void
    {
        if (! RecordAccess::active($actor)) {
            $query->whereRaw('1 = 0');
        } elseif ($actor->role === 'student') {
            $query->where('student_id', $actor->id);
        } elseif ($actor->role === 'teacher') {
            $query->whereExists(fn ($assignments) => $assignments->selectRaw('1')->from('teacher_assignments')
                ->join('terms', 'terms.id', '=', 'teacher_assignments.term_id')
                ->whereColumn('teacher_assignments.school_class_id', 'student_enrollments.school_class_id')
                ->where('teacher_assignments.teacher_id', $actor->id)->whereNull('teacher_assignments.revoked_at')
                ->whereColumn('student_enrollments.starts_on', '<=', 'terms.ends_on')
                ->where(fn ($ends) => $ends->whereNull('student_enrollments.ends_on')
                    ->orWhereColumn('student_enrollments.ends_on', '>=', 'terms.starts_on')));
        }
    }

    /** @param Builder<StudentEnrollment> $query */
    public function scopeInContext(Builder $query, TeacherAssignment $assignment): void
    {
        $query->where('school_class_id', $assignment->school_class_id)
            ->whereDate('starts_on', '<=', $assignment->term->ends_on->toDateString())
            ->where(fn ($ends) => $ends->whereNull('ends_on')->orWhereDate('ends_on', '>=', $assignment->term->starts_on->toDateString()));
    }

    /** @param Builder<StudentEnrollment> $query */
    public function scopeOnDate(Builder $query, string $date): void
    {
        $query->whereDate('starts_on', '<=', $date)
            ->where(fn ($q) => $q->whereDate('ends_on', '>=', $date)->orWhereNull('ends_on'))
            ->whereHas('schoolClass.academicYear', fn ($q) => $q->whereDate('ends_on', '>=', $date));
    }
}
