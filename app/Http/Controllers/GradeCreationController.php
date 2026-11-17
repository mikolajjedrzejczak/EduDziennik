<?php

namespace App\Http\Controllers;

use App\Http\Requests\GradeFormContextRequest;
use App\Http\Requests\StoreGradeRequest;
use App\Models\Assessment;
use App\Models\Grade;
use App\Models\GradeCategory;
use App\Models\StudentEnrollment;
use App\Services\Authorization\GradeAccess;
use App\Services\Grades\GradeFormContext;
use App\Services\Grades\GradeService;
use App\Services\Grades\TeacherGradebook;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class GradeCreationController extends Controller
{
    public function create(GradeFormContextRequest $request, GradeFormContext $resolver, GradeAccess $access, TeacherGradebook $gradebook): Response|RedirectResponse
    {
        $actor = $request->user();
        [$assignment, $assessment, $enrollment] = $resolver->resolve($actor, $request->validated('assignment_id'), $request->validated('assessment_id'), $request->validated('student_enrollment_id'));
        $cancel = route($actor->role.'.gradebooks.show', $assignment);
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
        $background = $gradebook->props($actor, $assignment, []);
        if ($background instanceof RedirectResponse) {
            return $background;
        }
        $present = fn (Assessment $a) => ['id' => (string) $a->id, 'topic' => $a->topic, 'category' => ['id' => (string) $a->category_id, 'label' => $a->category->name], 'defaultWeight' => $a->default_weight, 'gradedOn' => $a->graded_on->toDateString(), 'canDelete' => false];

        return Inertia::render('shared/grades/create', [
            'grade' => null, 'context' => $background['context'], 'assessment' => $assessment ? $present($assessment) : null,
            'selectedEnrollmentId' => $enrollment ? (string) $enrollment->id : null,
            'students' => $students->map(fn (StudentEnrollment $e) => ['id' => (string) $e->student_id, 'enrollmentId' => (string) $e->id, 'name' => $e->student->name, 'historical' => $e->isHistorical($assignment->term->academicYear->ends_on)])->all(),
            'assessments' => $assessments->map($present)->all(),
            'categories' => GradeCategory::where('is_active', true)->orderBy('name')->get()->map(fn ($c) => ['id' => (string) $c->id, 'label' => $c->name])->all(),
            'background' => $background, 'submitUrl' => route($actor->role.'.grades.store'), 'cancelUrl' => $cancel,
        ]);
    }

    public function store(StoreGradeRequest $request, GradeFormContext $resolver, GradeService $service): RedirectResponse
    {
        $data = $request->validated();
        [$assignment, $assessment, $enrollment] = $resolver->resolve($request->user(), $data['assignment_id'], $data['assessment_id'], $data['student_enrollment_id']);
        $fields = $request->safe()->only(['value', 'weight', 'category_id', 'topic', 'comment', 'graded_on']);
        foreach (['value', 'weight', 'category_id'] as $key) {
            $fields[$key] = (int) $fields[$key];
        }
        $service->create($request->user()->id, $enrollment->id, $assessment->id, $fields);

        return redirect()->route($request->user()->role.'.gradebooks.show', $assignment, 303)->with('success', 'Ocena została zapisana.');
    }
}
