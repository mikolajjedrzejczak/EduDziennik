<?php

namespace App\Services\Statistics;

use App\Models\Assessment;
use App\Models\Grade;
use App\Models\TeacherAssignment;
use App\Models\Term;
use App\Models\User;
use App\Services\Grades\GradePresenter;
use App\Services\Grades\TeacherClassList;
use Illuminate\Support\Facades\DB;

class TeacherDashboard
{
    public function __construct(private readonly TeacherClassList $classes, private readonly GradePresenter $presenter) {}

    /** @return array<string, mixed> */
    public function props(User $teacher, ?int $termId): array
    {
        $terms = $this->classes->terms($teacher);
        $term = $terms->firstWhere('id', $termId ?? $this->classes->defaultTermId($terms));
        $props = [
            'term' => $term === null ? null : $this->classes->termOption($term),
            'terms' => $terms->map(fn (Term $t) => $this->classes->termOption($t))->all(),
            'assignmentCount' => 0, 'ungradedCount' => 0, 'atRiskCount' => 0,
            'assignments' => [], 'recentGrades' => [],
        ];
        if ($term === null) {
            return $props;
        }

        $props['assignmentCount'] = TeacherAssignment::visibleTo($teacher)->active()->where('term_id', $term->id)->count();
        $props['assignments'] = $this->classes->assignments($teacher, $term->id, '', 'asc')->limit(5)->get()
            ->map(fn (TeacherAssignment $a) => $this->classes->present($a))->all();

        // EXISTS-based scope avoids double counting co-teachers. A deleted grade is an empty cell.
        $props['ungradedCount'] = Assessment::visibleTo($teacher)->where('term_id', $term->id)
            ->join('student_enrollments as e', 'e.school_class_id', '=', 'assessments.school_class_id')
            ->join('school_classes as c', 'c.id', '=', 'e.school_class_id')
            ->join('academic_years as y', 'y.id', '=', 'c.academic_year_id')
            ->whereColumn('e.starts_on', '<=', 'assessments.graded_on')
            ->whereRaw('COALESCE(e.ends_on, y.ends_on) >= assessments.graded_on')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('grades')
                ->whereColumn('grades.assessment_id', 'assessments.id')
                ->whereColumn('grades.student_enrollment_id', 'e.id')->whereNull('grades.deleted_at'))->count();

        $grades = Grade::visibleTo($teacher)->whereHas('assessment', fn ($q) => $q->where('term_id', $term->id));
        // Group by student, not enrollment: a transfer preserves the subject result in the visible scope.
        $atRisk = (clone $grades)->toBase()
            ->join('assessments as a', 'a.id', '=', 'grades.assessment_id')
            ->join('student_enrollments as e', 'e.id', '=', 'grades.student_enrollment_id')
            ->select('e.student_id', 'a.subject_id')->groupBy('e.student_id', 'a.subject_id')
            ->havingRaw('SUM(grades.value * grades.weight) < 2 * SUM(grades.weight)');
        $props['atRiskCount'] = DB::query()->fromSub($atRisk, 'at_risk')->count();
        $props['recentGrades'] = (clone $grades)
            ->with(['assessment.subject', 'assessment.term.academicYear', 'assessment.schoolClass', 'category', 'issuer', 'enrollment.student'])
            ->orderByDesc('grades.graded_on')->orderByDesc('grades.id')->limit(5)->get()
            // Dashboard links only to details; mutation/history abilities are resolved on that page.
            ->map(fn (Grade $g) => $this->presenter->toArray($g, $teacher, false, true))->all();

        return $props;
    }
}
