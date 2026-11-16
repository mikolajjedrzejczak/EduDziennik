<?php

namespace App\Services\Statistics;

use App\Models\GradeAudit;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Services\Grades\AuditPresenter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class AdminDashboard
{
    /** @return Collection<int, Term> */
    public function terms(User $actor): Collection
    {
        Gate::forUser($actor)->authorize('viewAny', SchoolClass::class);

        return Term::with('academicYear')->orderByDesc('starts_on')->orderByDesc('id')->get();
    }

    /** @return array{id: string, label: string} */
    private function termOption(Term $term): array
    {
        return ['id' => (string) $term->id, 'label' => 'Semestr '.$term->number.' · '.$term->academicYear->name.($term->closed_at ? ' · zamknięty' : '')];
    }

    /** @return array<string, mixed> */
    public function props(User $actor, ?int $termId): array
    {
        foreach ([User::class, SchoolClass::class, Subject::class, GradeAudit::class] as $model) {
            Gate::forUser($actor)->authorize('viewAny', $model);
        }
        $terms = $this->terms($actor);
        $today = now('Europe/Warsaw')->toDateString();
        $term = $termId === null
            ? $terms->first(fn (Term $t) => $t->starts_on->toDateString() <= $today && $t->ends_on->toDateString() >= $today)
            : $terms->firstWhere('id', $termId);
        $counts = User::query()->selectRaw("role, COUNT(*) AS total, SUM(status = 'inactive') AS inactive")->groupBy('role')->toBase()->get();
        $userCounts = ['student' => 0, 'teacher' => 0, 'admin' => 0];
        $inactiveCount = 0;
        foreach ($counts as $count) {
            $userCounts[$count->role] = (int) $count->total;
            $inactiveCount += (int) $count->inactive;
        }

        return [
            'term' => $term === null ? null : $this->termOption($term),
            'terms' => $terms->map(fn (Term $t) => $this->termOption($t))->all(),
            'userCounts' => $userCounts, 'inactiveCount' => $inactiveCount,
            'classCount' => $term === null ? 0 : SchoolClass::visibleTo($actor)->where('academic_year_id', $term->academic_year_id)->count(),
            'subjectCount' => Subject::visibleTo($actor)->count(),
            'recentAudits' => $term === null ? [] : GradeAudit::visibleTo($actor)
                ->whereHas('grade', fn ($g) => $g->withTrashed()->whereHas('assessment', fn ($a) => $a->withTrashed()->where('term_id', $term->id)))
                ->with(['actor', 'grade.assessment.subject', 'grade.enrollment.student'])
                ->orderByDesc('occurred_at')->orderByDesc('id')->limit(5)->get()
                ->map(fn (GradeAudit $audit) => app(AuditPresenter::class)->toArray($audit, $actor))->all(),
        ];
    }
}
