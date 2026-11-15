<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GradeAudit extends DomainModel
{
    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['occurred_at' => 'immutable_datetime', 'old_values' => 'array', 'new_values' => 'array', 'grade_version' => 'integer'];
    }

    /** @return BelongsTo<Grade, $this> */
    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class, 'grade_id')->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id')->withTrashed();
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Historia ocen jest niezmienna.'));
        static::deleting(fn () => throw new \LogicException('Historia ocen jest niezmienna.'));
    }
}
