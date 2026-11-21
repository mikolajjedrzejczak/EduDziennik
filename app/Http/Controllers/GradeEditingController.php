<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeleteGradeRequest;
use App\Http\Requests\UpdateGradeRequest;
use App\Models\Grade;
use App\Models\GradeCategory;
use App\Models\TeacherAssignment;
use App\Services\Grades\GradeFormContext;
use App\Services\Grades\GradePresenter;
use App\Services\Grades\GradeService;
use App\Services\Grades\TeacherGradebook;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class GradeEditingController extends GradeCreationController
{
    public function edit(Request $request, Grade $grade, GradeFormContext $resolver, GradePresenter $presenter, TeacherGradebook $gradebook): Response|RedirectResponse
    {
        Gate::authorize('update', $grade);
        $actor = $request->user();
        $a = $grade->assessment;
        $assignmentId = $request->query('assignment_id') ?? TeacherAssignment::visibleTo($actor)->forAssessment($a)->orderBy('id')->value('id');
        [$assignment] = $resolver->resolve($actor, $assignmentId, $grade->assessment_id, $grade->student_enrollment_id);
        $background = $gradebook->props($actor, $assignment, []);
        if ($background instanceof RedirectResponse) {
            return $background;
        }
        $dto = $presenter->toArray($grade, $actor);
        $assessment = $this->assessmentOption($a);

        return Inertia::render('shared/grades/edit', [
            'grade' => $dto, 'context' => $background['context'], 'assessment' => $assessment,
            'selectedEnrollmentId' => (string) $grade->student_enrollment_id,
            'students' => [$dto['student']], 'assessments' => [$assessment],
            'categories' => GradeCategory::activeOptions(),
            'background' => $background, 'submitUrl' => route($actor->role.'.grades.update', $grade),
            'cancelUrl' => route($actor->role.'.grades.show', $grade),
            'deleteUrl' => route($actor->role.'.grades.destroy', $grade),
        ]);
    }

    public function update(UpdateGradeRequest $request, Grade $grade, GradeService $service): RedirectResponse
    {
        $version = (int) $request->validated('version');
        $updated = $service->update($request->user()->id, $grade->id, $version, $request->validated('reason'), $request->gradeFields());
        $message = $updated->version === $version ? 'Ocena nie została zmieniona.' : 'Zmiany zostały zapisane. Wpis dodano do historii zmian.';

        return redirect()->route($request->user()->role.'.grades.show', $grade, 303)->with('success', $message);
    }

    public function destroy(DeleteGradeRequest $request, Grade $grade, GradeService $service): RedirectResponse
    {
        $data = $request->validated();
        $service->delete($request->user()->id, $grade->id, (int) $data['version'], $data['reason']);

        return redirect()->route($request->user()->role.'.gradebooks.show', ['assignment' => $data['assignment_id']], 303)
            ->with('success', 'Ocena została usunięta. Wpis zachowano w historii zmian.');
    }
}
