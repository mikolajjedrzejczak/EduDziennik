<?php

namespace App\Services\Grades;

use App\Models\Grade;
use App\Models\User;

class GradeSummary
{
    /** @return array{average: string|null, count: int} */
    public function forStudent(User $actor, int $studentId, int $termId, ?int $subjectId = null): array
    {
        $row = Grade::visibleTo($actor)->whereHas('enrollment', fn ($q) => $q->where('student_id', $studentId))
            ->whereHas('assessment', fn ($q) => $q->where('term_id', $termId)->when($subjectId !== null, fn ($s) => $s->where('subject_id', $subjectId)))
            ->toBase()->selectRaw('CAST(ROUND(SUM(value * weight) / NULLIF(SUM(weight), 0), 2) AS DECIMAL(10,2)) AS average, COUNT(*) AS count')->first();

        return ['average' => $row->average === null ? null : (string) $row->average, 'count' => (int) $row->count];
    }
}
