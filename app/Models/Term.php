<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\TermFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable $ends_on
 * @property CarbonImmutable|null $closed_at
 */
class Term extends DomainModel
{
    /** @use HasFactory<TermFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['starts_on' => 'immutable_date', 'ends_on' => 'immutable_date', 'closed_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<AcademicYear, $this> */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }

    /** @return HasMany<Assessment, $this> */
    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class, 'term_id');
    }

    /** @return HasMany<TeacherAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(TeacherAssignment::class, 'term_id');
    }

    /** @param Builder<Term> $query */
    public function scopeOnDate(Builder $query, ?string $date = null): void
    {
        $date ??= now('Europe/Warsaw')->toDateString();
        $query->whereDate('starts_on', '<=', $date)->whereDate('ends_on', '>=', $date);
    }

    protected static function booted(): void
    {
        static::updating(function (Term $term) {
            if ($term->isDirty(['academic_year_id', 'number', 'starts_on', 'ends_on']) || ($term->getOriginal('closed_at') !== null && $term->isDirty('closed_at'))) {
                throw new \LogicException('Daty semestru i zamknięcie są nieodwracalne.');
            }
        });
    }
}
