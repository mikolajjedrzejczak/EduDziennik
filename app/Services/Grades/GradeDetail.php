<?php

namespace App\Services\Grades;

use App\Models\Grade;
use App\Models\GradeAudit;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class GradeDetail
{
    public function __construct(private readonly GradePresenter $presenter, private readonly GradeSummary $summary) {}

    /** @return array<string, mixed> */
    public function props(User $actor, Grade $grade, int $historyPage = 1): array
    {
        Gate::forUser($actor)->authorize('view', $grade);
        $grade->load(['assessment.subject', 'assessment.schoolClass', 'assessment.term.academicYear', 'enrollment.student', 'category', 'issuer']);
        $a = $grade->assessment;
        $query = Grade::visibleTo($actor)
            ->whereHas('enrollment', fn ($q) => $q->where('student_id', $grade->enrollment->student_id))
            ->whereHas('assessment', fn ($q) => $q->where('subject_id', $a->subject_id)->where('term_id', $a->term_id));
        $summary = $this->summary->aggregate(clone $query);
        $without = $this->summary->aggregate((clone $query)->where('grades.id', '!=', $grade->id));
        Gate::forUser($actor)->authorize('viewHistory', $grade);
        $auditQuery = GradeAudit::visibleTo($actor)->where('grade_id', $grade->id);
        $total = $auditQuery->count();
        $historyPage = min($historyPage, max(1, (int) ceil($total / 10)));
        $audits = $auditQuery->with('actor')->orderByDesc('occurred_at')->orderByDesc('id')
            ->paginate(10, pageName: 'history_page', page: $historyPage, total: $total);
        $url = route($actor->role.'.grades.show', $grade, absolute: false);
        $audits->withPath($url)->fragment('grade-history');

        return [
            'grade' => $this->presenter->toArray($grade, $actor),
            'summary' => [...$summary, 'scopeLabel' => $a->subject->name.' · Semestr '.($a->term->number === 1 ? 'I' : 'II').' ('.$a->term->academicYear->name.')'],
            'averageWithoutGrade' => $without['average'],
            'historyUrl' => route($actor->role.'.grades.history', $grade, absolute: false),
            'backUrl' => $actor->role === 'student'
                ? route('student.grades.index', ['term_id' => $a->term_id, 'subject_id' => $a->subject_id], absolute: false)
                : route($actor->role.'.dashboard', absolute: false),
            'history' => [
                // A small audit projection for F-103. Full snapshots and filters belong to F-104.
                'data' => $audits->map(fn (GradeAudit $audit) => [
                    'id' => (string) $audit->id, 'action' => $audit->action,
                    'actor' => ['id' => (string) $audit->actor_id, 'label' => $audit->actor->name],
                    'occurredAt' => $audit->occurred_at->toIso8601String(),
                    'oldValue' => $audit->old_values['value'] ?? null,
                    'value' => $audit->new_values['value'], 'weight' => $audit->new_values['weight'],
                    'reason' => $audit->reason,
                ])->all(),
                'meta' => ['currentPage' => $audits->currentPage(), 'perPage' => 10, 'total' => $audits->total(), 'lastPage' => $audits->lastPage()],
                'links' => [
                    ['label' => 'Poprzednia', 'url' => $audits->previousPageUrl(), 'active' => false],
                    ['label' => 'Następna', 'url' => $audits->nextPageUrl(), 'active' => false],
                ],
            ],
        ];
    }
}
