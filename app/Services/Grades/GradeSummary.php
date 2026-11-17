<?php

namespace App\Services\Grades;

use App\Models\Grade;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class GradeSummary
{
    /** BR-03: SUM(value * weight) / SUM(weight) over non-deleted grades, rounded half-up to 2 places in SQL. */
    private const AVERAGE_SQL = 'CAST(ROUND(SUM(grades.value * grades.weight) / NULLIF(SUM(grades.weight), 0), 2) AS DECIMAL(10,2))';

    /** @return array{average: string|null, count: int} */
    public function forStudent(User $actor, int $studentId, int $termId, ?int $subjectId = null): array
    {
        return $this->aggregate(Grade::visibleTo($actor)->whereHas('enrollment', fn ($q) => $q->where('student_id', $studentId))
            ->whereHas('assessment', fn ($q) => $q->where('term_id', $termId)->when($subjectId !== null, fn ($s) => $s->where('subject_id', $subjectId))));
    }

    /**
     * Aggregate an already authorized and filtered grade query. Pagination must never be applied to it.
     *
     * @param  Builder<Grade>  $grades
     * @return array{average: string|null, count: int}
     */
    public function aggregate(Builder $grades): array
    {
        $row = $grades->toBase()->selectRaw(self::AVERAGE_SQL.' AS average, COUNT(*) AS count')->first();

        return ['average' => $row->average === null ? null : (string) $row->average, 'count' => (int) $row->count];
    }

    /**
     * @param  Builder<Grade>  $grades  Already authorized, restricted to the displayed enrollments, without pagination.
     * @return array<int, array{average: string|null, count: int}>
     */
    public function byEnrollment(Builder $grades): array
    {
        return $grades->toBase()->groupBy('grades.student_enrollment_id')
            ->selectRaw('grades.student_enrollment_id, '.self::AVERAGE_SQL.' AS average, COUNT(*) AS count')->get()
            ->mapWithKeys(fn ($row) => [(int) $row->student_enrollment_id => [
                'average' => $row->average === null ? null : (string) $row->average, 'count' => (int) $row->count,
            ]])->all();
    }

    /**
     * Per-subject aggregates of an already authorized and filtered grade query, keyed by subject id.
     *
     * @param  Builder<Grade>  $grades
     * @return array<int, array{average: string|null, count: int}>
     */
    public function bySubject(Builder $grades): array
    {
        $rows = $grades->toBase()->join('assessments', 'assessments.id', '=', 'grades.assessment_id')
            ->groupBy('assessments.subject_id')
            ->selectRaw('assessments.subject_id AS subject_id, '.self::AVERAGE_SQL.' AS average, COUNT(*) AS count')->get();

        return $rows->mapWithKeys(fn ($row) => [(int) $row->subject_id => [
            'average' => $row->average === null ? null : (string) $row->average, 'count' => (int) $row->count,
        ]])->all();
    }

    /**
     * BR-03: highest first using the unrounded ratio, then name/id for ties.
     *
     * @param  Builder<Grade>  $grades
     * @return list<array{subject: array{id: string, label: string}, average: string|null, count: int}>
     */
    public function rankedSubjects(Builder $grades): array
    {
        return array_values($grades->toBase()
            ->join('assessments', 'assessments.id', '=', 'grades.assessment_id')
            ->join('subjects', 'subjects.id', '=', 'assessments.subject_id')
            ->groupBy('subjects.id', 'subjects.name')
            ->selectRaw('subjects.id, subjects.name, '.self::AVERAGE_SQL.' AS average, COUNT(*) AS count')
            ->orderByRaw('SUM(grades.value * grades.weight) / NULLIF(SUM(grades.weight), 0) DESC')
            ->orderBy('subjects.name')->orderBy('subjects.id')->get()
            ->map(fn ($row) => [
                'subject' => ['id' => (string) $row->id, 'label' => (string) $row->name],
                'average' => $row->average === null ? null : (string) $row->average,
                'count' => (int) $row->count,
            ])->all());
    }
}
