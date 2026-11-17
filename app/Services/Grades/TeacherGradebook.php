<?php

namespace App\Services\Grades;

use App\Models\Assessment;
use App\Models\Grade;
use App\Models\GradeCategory;
use App\Models\StudentEnrollment;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Services\Authorization\GradeAccess;
use App\Services\Authorization\GradebookContext;
use App\Support\ListFilters;
use App\Support\PagePresenter;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/** Shared teacher/admin read model for an exact teaching context. */
class TeacherGradebook
{
    public function __construct(private readonly GradeAccess $access, private readonly PagePresenter $pages, private readonly GradePresenter $grades, private readonly GradeSummary $summary, private readonly TeacherClassList $classes) {}

    /** @param array<string, mixed> $data */
    public function render(User $actor, TeacherAssignment $assignment, array $data): Response|RedirectResponse
    {
        $props = $this->props($actor, $assignment, $data);

        return $props instanceof RedirectResponse ? $props : Inertia::render('shared/gradebooks/show', $props);
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>|RedirectResponse
     */
    public function props(User $actor, TeacherAssignment $assignment, array $data): array|RedirectResponse
    {
        $read = new GradebookContext($actor, $this->access, $assignment->id);
        $assignment = $read->assignment;
        $direction = $data['direction'] ?? 'asc';
        $search = trim($data['q'] ?? '');
        $like = ListFilters::contains($search);
        $students = StudentEnrollment::visibleTo($actor)->inContext($assignment)->with('student')
            ->when($search !== '', fn ($q) => $q->whereHas('student', fn ($s) => $s->where('last_name', 'like', $like)))
            ->orderBy(User::withTrashed()->select('last_name')->whereColumn('users.id', 'student_enrollments.student_id'), $direction)
            ->orderBy('student_enrollments.id', $direction)
            ->paginate((int) ($data['per_page'] ?? 10), page: (int) ($data['page'] ?? 1));
        $assessments = Assessment::visibleTo($actor)->where([
            'school_class_id' => $assignment->school_class_id, 'subject_id' => $assignment->subject_id, 'term_id' => $assignment->term_id,
        ])->with('category')->withExists(['grades as has_grades' => fn ($q) => $q->withTrashed()])
            ->orderBy('graded_on')->orderBy('id')->paginate(10, pageName: 'assessment_page', page: (int) ($data['assessment_page'] ?? 1));
        $corrected = $data;
        foreach (['page' => $students, 'assessment_page' => $assessments] as $key => $page) {
            if ($page->currentPage() > $page->lastPage()) {
                $corrected[$key] = $page->lastPage();
            }
        }
        if ($corrected !== $data) {
            return redirect()->route($actor->role.'.gradebooks.show', ['assignment' => $assignment->id, ...$corrected], 303);
        }
        $context = [
            'assignmentId' => (string) $assignment->id,
            'schoolClass' => ['id' => (string) $assignment->school_class_id, 'label' => $assignment->schoolClass->name],
            'subject' => ['id' => (string) $assignment->subject_id, 'label' => $assignment->subject->name],
            'term' => $this->classes->termOption($assignment->term), 'closed' => $assignment->term->closed_at !== null,
        ];
        $scope = $assignment->schoolClass->name.' · '.$assignment->subject->name.' · '.$context['term']['label'];
        $base = Grade::visibleTo($actor)->whereHas('assessment', fn ($a) => $a->where([
            'school_class_id' => $assignment->school_class_id, 'subject_id' => $assignment->subject_id, 'term_id' => $assignment->term_id,
        ]));
        $visible = (clone $base)->withTrashed()->whereIn('student_enrollment_id', $students->getCollection()->pluck('id'))
            ->whereIn('assessment_id', $assessments->getCollection()->pluck('id'))
            ->with(['assessment.subject', 'assessment.term.academicYear', 'assessment.schoolClass', 'category', 'issuer', 'enrollment.student'])->get();

        $summaries = $this->summary->byEnrollment((clone $base)->whereIn('student_enrollment_id', $students->getCollection()->pluck('id')));

        return [
            'context' => $context,
            'canCreateAssessment' => $read->canCreateAssessment(),
            'assessments' => $this->pages->present($assessments->appends($data), fn (Assessment $a) => [
                'id' => (string) $a->id, 'topic' => $a->topic, 'category' => ['id' => (string) $a->category_id, 'label' => $a->category->name],
                'defaultWeight' => $a->default_weight, 'gradedOn' => $a->graded_on->toDateString(), 'canDelete' => ! $a->getAttribute('has_grades') && $read->writable($a),
            ]),
            'students' => $this->pages->present($students->appends($data), function (StudentEnrollment $e) use ($actor, $visible, $summaries, $scope, $context, $assessments, $read, $assignment) {
                $grades = $visible->where('student_enrollment_id', $e->id);

                return [
                    'student' => ['id' => (string) $e->student_id, 'enrollmentId' => (string) $e->id, 'name' => $e->student->name,
                        'historical' => $e->isHistorical($assignment->term->academicYear->ends_on)],
                    'grades' => $grades->filter(fn (Grade $g) => ! $g->trashed())->map(fn (Grade $g) => [...$this->grades->toArray($g, $actor, capabilities: $read->capabilities($g)), 'context' => $context])->values()->all(),
                    'creatableAssessmentIds' => $assessments->getCollection()->filter(fn (Assessment $a) => ! $grades->contains('assessment_id', $a->id) && $read->writable($a, $e))->map(fn (Assessment $a) => (string) $a->id)->values()->all(),
                    'deletedAssessmentIds' => $grades->filter(fn (Grade $g) => $g->trashed())->map(fn (Grade $g) => (string) $g->assessment_id)->values()->all(),
                    'summary' => [...($summaries[$e->id] ?? ['average' => null, 'count' => 0]), 'scopeLabel' => $scope],
                ];
            }),
            'filters' => ['q' => $search, 'page' => $students->currentPage(), 'perPage' => $students->perPage(), 'assessmentPage' => $assessments->currentPage(), 'sort' => 'last_name', 'direction' => $direction],
            'categories' => GradeCategory::where('is_active', true)->orderBy('name')->get()->map(fn (GradeCategory $c) => ['id' => (string) $c->id, 'label' => $c->name]),
        ];
    }
}
