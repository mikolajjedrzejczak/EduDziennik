<?php

namespace App\Models;

use Database\Factories\GradeCategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GradeCategory extends DomainModel
{
    /** @use HasFactory<GradeCategoryFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['default_weight' => 'integer', 'is_active' => 'boolean'];
    }

    /** @param Builder<GradeCategory> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @return list<int> */
    public static function activeIds(): array
    {
        return array_values(array_map(intval(...), self::query()->active()->pluck('id')->all()));
    }

    /** @return list<array{id: string, label: string}> categories selectable in grade and column forms */
    public static function activeOptions(): array
    {
        return array_values(self::query()->active()->orderBy('name')->get()
            ->map(fn (GradeCategory $category) => ['id' => (string) $category->id, 'label' => $category->name])->all());
    }

    /** @return HasMany<Assessment, $this> */
    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class, 'category_id');
    }

    /** @return HasMany<Grade, $this> */
    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class, 'category_id');
    }
}
