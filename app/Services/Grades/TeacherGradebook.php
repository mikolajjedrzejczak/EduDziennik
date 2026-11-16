<?php

namespace App\Services\Grades;

use App\Models\Assessment;
use App\Models\Grade;
use App\Models\GradeCategory;
use App\Models\StudentEnrollment;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Services\Authorization\GradeAccess;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/** Read-only destination for selecting an exact assignment. Grade mutations belong to F-203–206. */
class TeacherGradebook
{
    public function __construct(private readonly GradeAccess $access, private readonly ReadPage $pages, private readonly GradePresenter $grades, private readonly GradeSummary $summary, private readonly TeacherClassList $classes) {}

    /** @param array<string, mixed> $data */
    public function render(User $actor, TeacherAssignment $assignment, array $data): Response|RedirectResponse
    {
        $assignment = $this->access->assignment($actor, $assignment->id);
        $direction = $data['direction'] ?? 'asc';
        $search = trim($data['q'] ?? '');
        $like = '%'.addcslashes($search, '\\%_').'%';
        $students = $this->access->enrollments($actor, $assignment)->with('student')
            ->when($search !== '', fn ($q) => $q->whereHas('student', fn ($s) => $s->where('last_name', 'like', $like)))
            ->orderBy(User::withTrashed()->select('last_name')->whereColumn('users.id', 'student_enrollments.student_id'), $direction)
            ->orderBy('student_enrollments.id', $direction)
            ->paginate((int) ($data['per_page'] ?? 10), page: (int) ($data['page'] ?? 1));
        $assessments = $this->access->assessments($actor, $assignment)->with('category')
            ->orderBy('graded_on')->orderBy('id')->paginate(10, pageName: 'assessment_page', page: (int) ($data['assessment_page'] ?? 1));
        $corrected = $data;
        foreach (['page' => $students, 'assessment_page' => $assessments] as $key => $page) {
            if ($page->currentPage() > $page->lastPage()) {
                $corrected[$key] = $page->lastPage();
            }
        }
        if ($corrected !== $data) {
            return redirect()->route('teacher.gradebooks.show', ['assignment' => $assignment->id, ...$corrected], 303);
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

        return Inertia::render('shared/gradebooks/show', [
            'context' => $context,
            'assessments' => $this->pages->present($assessments->appends($data), fn (Assessment $a) => [
                'id' => (string) $a->id, 'topic' => $a->topic, 'category' => ['id' => (string) $a->category_id, 'label' => $a->category->name],
                'defaultWeight' => $a->default_weight, 'gradedOn' => $a->graded_on->toDateString(),
            ]),
            'students' => $this->pages->present($students->appends($data), function (StudentEnrollment $e) use ($actor, $visible, $base, $scope, $context) {
                $grades = $visible->where('student_enrollment_id', $e->id);

                return [
                    'student' => ['id' => (string) $e->student_id, 'enrollmentId' => (string) $e->id, 'name' => $e->student->name,
                        'historical' => $e->ends_on !== null && $e->ends_on->toDateString() < now('Europe/Warsaw')->toDateString()],
                    'grades' => $grades->filter(fn (Grade $g) => ! $g->trashed())->map(fn (Grade $g) => [...$this->grades->toArray($g, $actor), 'context' => $context])->values()->all(),
                    'deletedAssessmentIds' => $grades->filter(fn (Grade $g) => $g->trashed())->map(fn (Grade $g) => (string) $g->assessment_id)->values()->all(),
                    'summary' => [...$this->summary->aggregate((clone $base)->where('student_enrollment_id', $e->id)), 'scopeLabel' => $scope],
                ];
            }),
            'filters' => ['q' => $search, 'page' => $students->currentPage(), 'perPage' => $students->perPage(), 'assessmentPage' => $assessments->currentPage(), 'sort' => 'last_name', 'direction' => $direction],
            'categories' => GradeCategory::where('is_active', true)->orderBy('name')->get()->map(fn (GradeCategory $c) => ['id' => (string) $c->id, 'label' => $c->name]),
        ]);
    }
}
