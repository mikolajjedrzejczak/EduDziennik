<?php

namespace App\Services\Grades;

use App\Models\Grade;
use App\Models\GradeCategory;
use App\Models\SchoolClass;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Support\ListFilters;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class StaffGradeList
{
    /** @return Collection<int, Term> */
    public function terms(User $actor): Collection
    {
        return $actor->role === 'admin' ? Term::with('academicYear')->orderBy('starts_on')->get() : app(TeacherClassList::class)->terms($actor);
    }

    /** @return list<int> */
    public function ids(User $actor, string $field): array
    {
        $query = Grade::visibleTo($actor);

        return match ($field) {
            'subject_id', 'school_class_id' => $query->join('assessments', 'assessments.id', '=', 'grades.assessment_id')->distinct()->pluck('assessments.'.$field)->all(),
            'student_id' => $query->join('student_enrollments', 'student_enrollments.id', '=', 'grades.student_enrollment_id')->distinct()->pluck('student_enrollments.student_id')->all(),
            default => $query->distinct()->pluck('grades.'.$field)->all(),
        };
    }

    /** @param array<string, mixed> $data */
    public function render(User $actor, array $data): Response|RedirectResponse
    {
        $terms = $this->terms($actor);
        $termId = isset($data['term_id']) ? (int) $data['term_id'] : app(TeacherClassList::class)->defaultTermId($terms);
        $query = Grade::visibleTo($actor)->whereHas('assessment', fn ($a) => $a->where('term_id', $termId)
            ->when(isset($data['subject_id']), fn ($q) => $q->where('subject_id', $data['subject_id']))
            ->when(isset($data['school_class_id']), fn ($q) => $q->where('school_class_id', $data['school_class_id'])))
            ->when(isset($data['category_id']), fn ($q) => $q->where('grades.category_id', $data['category_id']))
            ->when(isset($data['student_enrollment_id']), fn ($q) => $q->where('student_enrollment_id', $data['student_enrollment_id']))
            ->when(isset($data['student_id']), fn ($q) => $q->whereHas('enrollment', fn ($e) => $e->where('student_id', $data['student_id'])))
            ->when(isset($data['date_from']), fn ($q) => $q->where('grades.graded_on', '>=', $data['date_from']))
            ->when(isset($data['date_to']), fn ($q) => $q->where('grades.graded_on', '<=', $data['date_to']))
            ->when(trim($data['q'] ?? '') !== '', fn ($q) => $q->where('grades.topic', 'like', ListFilters::contains(trim($data['q']))));
        $summary = app(GradeSummary::class)->aggregate(clone $query);
        $page = $query->with(['assessment.subject', 'assessment.term.academicYear', 'assessment.schoolClass', 'category', 'issuer', 'enrollment.student'])
            ->orderBy('grades.'.($data['sort'] ?? 'graded_on'), $data['direction'] ?? 'desc')->orderBy('grades.id', $data['direction'] ?? 'desc')
            ->paginate((int) ($data['per_page'] ?? 10), page: (int) ($data['page'] ?? 1));
        $data['term_id'] = $termId;
        if ($page->currentPage() > $page->lastPage()) {
            return redirect()->route($actor->role.'.grades.index', [...$data, 'page' => $page->lastPage()], 303);
        }
        $option = fn ($m) => ['id' => (string) $m->id, 'label' => $m->name];
        $options = [
            'terms' => $terms->map(fn ($t) => app(TeacherClassList::class)->termOption($t))->values(),
            'subjects' => Subject::withTrashed()->whereIn('id', $this->ids($actor, 'subject_id'))->orderBy('name')->limit(501)->get()->map($option),
            'categories' => GradeCategory::whereIn('id', $this->ids($actor, 'category_id'))->orderBy('name')->limit(501)->get()->map($option),
            'classes' => $actor->role === 'admin' ? SchoolClass::withTrashed()->whereIn('id', $this->ids($actor, 'school_class_id'))->orderBy('name')->limit(501)->get()->map($option) : [],
            'students' => $actor->role === 'admin' ? User::withTrashed()->whereIn('id', $this->ids($actor, 'student_id'))->orderBy('last_name')->limit(501)->get()->map($option) : [],
            'enrollments' => StudentEnrollment::visibleTo($actor)->whereIn('id', $this->ids($actor, 'student_enrollment_id'))->with(['student', 'schoolClass'])->limit(501)->get()->map(fn ($e) => ['id' => (string) $e->id, 'label' => $e->student->name.' · '.$e->schoolClass->name]),
        ];
        foreach ($options as $dictionary) {
            abort_if(count($dictionary) > 500, 503);
        }
        $labels = [$terms->firstWhere('id', $termId) ? app(TeacherClassList::class)->termOption($terms->firstWhere('id', $termId))['label'] : 'Brak semestru'];
        foreach (['subject_id' => 'subjects', 'category_id' => 'categories', 'school_class_id' => 'classes', 'student_id' => 'students', 'student_enrollment_id' => 'enrollments'] as $key => $list) {
            if (isset($data[$key])) {
                $labels[] = collect($options[$list])->firstWhere('id', (string) $data[$key])['label'] ?? '';
            }
        }
        if (isset($data['date_from']) || isset($data['date_to'])) {
            $labels[] = 'daty: '.($data['date_from'] ?? '…').' – '.($data['date_to'] ?? '…');
        }
        if (trim($data['q'] ?? '') !== '') {
            $labels[] = 'temat: '.trim($data['q']);
        }

        return Inertia::render($actor->role.'/grades/index', [
            'grades' => app(ReadPage::class)->present($page->appends($data), fn (Grade $g) => app(GradePresenter::class)->toArray($g, $actor)),
            'summary' => [...$summary, 'scopeLabel' => implode(' · ', $labels)],
            'options' => $options,
            'filters' => ['termId' => $termId === null ? null : (string) $termId, 'subjectId' => isset($data['subject_id']) ? (string) $data['subject_id'] : null,
                'categoryId' => isset($data['category_id']) ? (string) $data['category_id'] : null, 'studentId' => isset($data['student_id']) ? (string) $data['student_id'] : null,
                'schoolClassId' => isset($data['school_class_id']) ? (string) $data['school_class_id'] : null, 'studentEnrollmentId' => isset($data['student_enrollment_id']) ? (string) $data['student_enrollment_id'] : null,
                'q' => trim($data['q'] ?? ''), 'dateFrom' => $data['date_from'] ?? null, 'dateTo' => $data['date_to'] ?? null,
                'page' => $page->currentPage(), 'perPage' => $page->perPage(), 'sort' => $data['sort'] ?? 'graded_on', 'direction' => $data['direction'] ?? 'desc'],
        ]);
    }
}
