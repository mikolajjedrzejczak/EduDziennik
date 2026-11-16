<?php

namespace App\Services\Statistics;

use App\Models\Grade;
use App\Models\Term;
use App\Models\User;
use App\Services\Grades\GradePresenter;
use App\Services\Grades\GradeSummary;
use App\Services\Grades\StudentGradeList;

class StudentDashboard
{
    public function __construct(private readonly StudentGradeList $list, private readonly GradeSummary $summary, private readonly GradePresenter $presenter) {}

    /** @return array<string, mixed> */
    public function props(User $student, ?int $termId): array
    {
        $terms = $this->list->terms($student);
        $today = now('Europe/Warsaw')->toDateString();
        $term = $termId === null
            ? $terms->first(fn (Term $t) => $t->starts_on->toDateString() <= $today && $t->ends_on->toDateString() >= $today)
            : $terms->firstWhere('id', $termId);
        $option = fn (Term $t) => ['id' => (string) $t->id, 'label' => 'Semestr '.($t->number === 1 ? 'I' : 'II').' ('.$t->academicYear->name.')'];
        $props = [
            'term' => $term === null ? null : $option($term),
            'terms' => $terms->map($option)->values()->all(),
            'summary' => ['average' => null, 'count' => 0, 'scopeLabel' => $term === null ? 'Brak semestru' : $option($term)['label']],
            'subjects' => [], 'recentGrades' => [],
        ];
        if ($term === null) {
            return $props;
        }

        // S is applied before every aggregate, join and limit; ended enrollments remain visible.
        $grades = Grade::visibleTo($student)->whereHas('assessment', fn ($q) => $q->where('term_id', $term->id));
        $props['summary'] = [...$this->summary->aggregate(clone $grades), 'scopeLabel' => $option($term)['label']];
        $props['subjects'] = $this->summary->rankedSubjects(clone $grades);
        $props['recentGrades'] = (clone $grades)
            ->with(['assessment.subject', 'assessment.term.academicYear', 'assessment.schoolClass', 'category', 'issuer', 'enrollment.student'])
            ->orderByDesc('grades.graded_on')->orderByDesc('grades.id')->limit(5)->get()
            ->map(fn (Grade $g) => $this->presenter->toArray($g, $student))->all();

        return $props;
    }
}
