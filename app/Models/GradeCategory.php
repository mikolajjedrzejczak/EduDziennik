<?php

namespace App\Models;

use Database\Factories\GradeCategoryFactory;
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
