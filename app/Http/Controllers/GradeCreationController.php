<?php

namespace App\Http\Controllers;

use App\Http\Requests\GradeFormContextRequest;
use App\Http\Requests\StoreGradeRequest;
use App\Models\Assessment;
use App\Models\Grade;
use App\Models\GradeCategory;
use App\Models\StudentEnrollment;
use App\Services\Authorization\GradeAccess;
use App\Services\Grades\GradeService;
use App\Services\Grades\TeacherGradebook;
use App\Support\ListFilters;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class GradeCreationController extends Controller
{
    public function create(GradeFormContextRequest $request, GradeAccess $access, TeacherGradebook $gradebook): Response|RedirectResponse
    {
        $actor = $request->user();
        [$assignment, $assessment, $enrollment] = $request->gradeFormContext();
        $filters = $this->gradebookFilters($request);
        $cancel = route($actor->role.'.gradebooks.show', ['assignment' => $assignment->id, ...$filters]);
        if ($assignment->term->closed_at !== null) {
            return redirect($cancel, 303)->withErrors(['form' => 'Semestr jest zamknięty. Nie można wystawić oceny.']);
        }
        $students = $access->enrollments($actor, $assignment)->when($enrollment, fn ($q) => $q->whereKey($enrollment->id))->with('student')->orderBy('id')->limit(51)->get();
        $assessments = $access->assessments($actor, $assignment)->when($assessment, fn ($q) => $q->whereKey($assessment->id))->with('category')->orderBy('graded_on')->orderBy('id')->limit(51)->get();
        if ($students->count() > 50 || $assessments->count() > 50) {
            return redirect($cancel, 303)->withErrors(['form' => 'Wybierz pustą komórkę w dzienniku, aby wystawić ocenę.']);
        }
        if ($assessment && $enrollment) {
            abort_unless($actor->can('create', [Grade::class, $assessment, $enrollment]), 403);
            if (Grade::withTrashed()->where('assessment_id', $assessment->id)->where('student_enrollment_id', $enrollment->id)->exists()) {
                return redirect($cancel, 303)->withErrors(['form' => 'Ta komórka ma już ocenę lub historię. Wybierz inną komórkę.']);
            }
        }
        $background = $gradebook->props($actor, $assignment, $filters);
        if ($background instanceof RedirectResponse) {
            return $background;
        }

        return Inertia::render('shared/grades/create', [
            'grade' => null, 'context' => $background['context'], 'assessment' => $assessment ? $this->assessmentOption($assessment) : null,
            'selectedEnrollmentId' => $enrollment ? (string) $enrollment->id : null,
            'students' => $students->map(fn (StudentEnrollment $e) => ['id' => (string) $e->student_id, 'enrollmentId' => (string) $e->id, 'name' => $e->student->name, 'historical' => $e->isHistorical($assignment->term->academicYear->ends_on)])->all(),
            'assessments' => $assessments->map(fn (Assessment $a) => $this->assessmentOption($a))->all(),
            'categories' => GradeCategory::activeOptions(),
            'returnFilters' => $filters, 'background' => $background, 'submitUrl' => route($actor->role.'.grades.store'), 'cancelUrl' => $cancel,
        ]);
    }

    public function store(StoreGradeRequest $request, GradeService $service): RedirectResponse
    {
        [$assignment, $assessment, $enrollment] = $request->gradeFormContext();
        $service->create($request->user()->id, $enrollment->id, $assessment->id, $request->gradeFields());

        return redirect()->route($request->user()->role.'.gradebooks.show', ['assignment' => $assignment->id, ...$this->gradebookFilters($request)], 303)->with('success', 'Ocena została zapisana.');
    }

    /** @return array<string, mixed> gradebook list state to restore after the form */
    protected function gradebookFilters(FormRequest $request): array
    {
        return $request->safe()->only(array_keys(ListFilters::gradebookRules()));
    }

    /** @return array<string, mixed> */
    protected function assessmentOption(Assessment $assessment): array
    {
        return [
            'id' => (string) $assessment->id, 'topic' => $assessment->topic,
            'category' => ['id' => (string) $assessment->category_id, 'label' => $assessment->category->name],
            'defaultWeight' => $assessment->default_weight, 'gradedOn' => $assessment->graded_on->toDateString(), 'canDelete' => false,
        ];
    }
}
