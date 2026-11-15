<?php

namespace App\Models;

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
    public function scopeOnDate(Builder $query, string $date): void
    {
        $query->whereDate('starts_on', '<=', $date)
            ->where(fn ($q) => $q->whereDate('ends_on', '>=', $date)->orWhereNull('ends_on'))
            ->whereHas('schoolClass.academicYear', fn ($q) => $q->whereDate('ends_on', '>=', $date));
    }
}
