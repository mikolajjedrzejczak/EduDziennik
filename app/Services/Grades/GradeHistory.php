<?php

namespace App\Services\Grades;

use App\Models\Assessment;
use App\Models\Grade;
use App\Models\GradeAudit;
use App\Models\GradeCategory;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Models\Term;
use App\Models\User;
use App\Services\Authorization\GradeAccess;
use App\Support\PagePresenter;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class GradeHistory
{
    /** @return array<string, array<int, array{id: string, label: string}>> */
    public function options(User $actor, ?int $enrollmentId = null): array
    {
        $assessments = Grade::withTrashed()->visibleTo($actor)->select('assessment_id');
        $context = Assessment::withTrashed()->whereIn('id', $assessments);
        $terms = Term::with('academicYear')->whereIn('id', (clone $context)->select('term_id'))->orderBy('starts_on')->orderBy('id')->limit(501)->get();
        $subjects = Subject::withTrashed()->whereIn('id', (clone $context)->select('subject_id'))->orderBy('name')->orderBy('id')->limit(501)->get();
        $categoryIds = GradeAudit::visibleTo($actor)->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(new_values, '$.category_id'))")
            ->union(GradeAudit::visibleTo($actor)->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(old_values, '$.category_id'))"));
        $categories = GradeCategory::whereIn('id', $categoryIds)->orderBy('name')->limit(501)->get();
        $enrollment = $enrollmentId === null ? null : StudentEnrollment::visibleTo($actor)->find($enrollmentId);
        if ($actor->role !== 'student' && $enrollment !== null) {
            $assignments = TeacherAssignment::visibleTo($actor)->where('school_class_id', $enrollment->school_class_id)
                ->whereHas('term', fn ($t) => $t->whereDate('ends_on', '>=', $enrollment->starts_on)->whereDate('starts_on', '<=', $enrollment->ends_on ?? $enrollment->schoolClass->academicYear->ends_on));
            $terms = $terms->merge(Term::with('academicYear')->whereIn('id', (clone $assignments)->select('term_id'))->limit(501)->get())->unique('id')->values();
            $subjects = $subjects->merge(Subject::withTrashed()->whereIn('id', (clone $assignments)->select('subject_id'))->limit(501)->get())->unique('id')->values();
        }
        abort_if($terms->count() > 500 || $subjects->count() > 500 || $categories->count() > 500, 503);

        return [
            'terms' => $terms->map(fn (Term $t) => ['id' => (string) $t->id, 'label' => 'Semestr '.($t->number === 1 ? 'I' : 'II').' ('.$t->academicYear->name.')'])->all(),
            'subjects' => $subjects->map(fn (Subject $s) => ['id' => (string) $s->id, 'label' => $s->name])->all(),
            // Include categories from both sides of visible historical changes.
            'categories' => $categories->map(fn (GradeCategory $c) => ['id' => (string) $c->id, 'label' => $c->name])->all(),
        ];
    }

    /** @param array<string, mixed> $filters */
    public function render(User $actor, array $filters, ?Grade $grade, string $url): Response|RedirectResponse
    {
        Gate::forUser($actor)->authorize('viewAny', GradeAudit::class);
        if ($grade === null && $filters['gradeId'] !== null) {
            $grade = app(GradeAccess::class)->grade($actor, (int) $filters['gradeId'], archived: true);
        }
        $query = app(GradeAccess::class)->history($actor, $grade)
            ->when($filters['action'], fn ($q, $action) => $q->where('action', $action))
            ->whereHas('grade', fn ($g) => $g->withTrashed()
                ->when($filters['studentEnrollmentId'], fn ($g, $id) => $g->where('student_enrollment_id', $id))
                ->whereHas('assessment', fn ($a) => $a
                    ->when($filters['termId'], fn ($a, $id) => $a->where('term_id', $id))
                    ->when($filters['subjectId'], fn ($a, $id) => $a->where('subject_id', $id))));
        if ($filters['dateFrom']) {
            $query->where('occurred_at', '>=', CarbonImmutable::parse($filters['dateFrom'], 'Europe/Warsaw')->startOfDay()->utc());
        }
        if ($filters['dateTo']) {
            $query->where('occurred_at', '<', CarbonImmutable::parse($filters['dateTo'], 'Europe/Warsaw')->addDay()->startOfDay()->utc());
        }
        $page = $query->with(['actor', 'grade.assessment.subject', 'grade.enrollment.student'])
            ->orderByDesc('occurred_at')->orderByDesc('id')->paginate($filters['perPage'], page: $filters['page']);
        $params = [];
        foreach (['gradeId' => 'grade_id', 'studentEnrollmentId' => 'student_enrollment_id', 'termId' => 'term_id', 'subjectId' => 'subject_id', 'action' => 'action', 'dateFrom' => 'date_from', 'dateTo' => 'date_to', 'perPage' => 'per_page'] as $key => $param) {
            if ($filters[$key] !== null) {
                $params[$param] = $filters[$key];
            }
        }
        $page->withPath($url)->appends($params);
        if ($page->isEmpty() && $filters['page'] > 1) {
            return redirect()->to($page->url($page->lastPage()), 303);
        }
        if ($grade) {
            $grade->load(['assessment.subject', 'assessment.schoolClass', 'assessment.term.academicYear', 'enrollment.student', 'category', 'issuer']);
        }

        return Inertia::render('shared/history/index', [
            'audits' => [
                'data' => $page->map(fn (GradeAudit $audit) => app(AuditPresenter::class)->toArray($audit, $actor))->all(),
                ...app(PagePresenter::class)->navigation($page),
            ],
            'filters' => $filters, 'options' => $this->options($actor, isset($filters['studentEnrollmentId']) ? (int) $filters['studentEnrollmentId'] : null),
            'grade' => $grade ? app(GradePresenter::class)->toArray($grade, $actor) : null,
            'indexUrl' => $url,
            'historyUrl' => route($actor->role.'.history.index', absolute: false),
        ]);
    }
}
