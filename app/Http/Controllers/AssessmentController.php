<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeleteAssessmentRequest;
use App\Http\Requests\StoreAssessmentRequest;
use App\Models\Assessment;
use App\Models\TeacherAssignment;
use App\Services\School\AssessmentService;
use Illuminate\Http\RedirectResponse;

class AssessmentController extends Controller
{
    public function store(StoreAssessmentRequest $request, TeacherAssignment $assignment, AssessmentService $service): RedirectResponse
    {
        $data = $request->validated();
        $service->create($request->user()->id, [...$data,
            'category_id' => (int) $data['category_id'], 'default_weight' => (int) $data['default_weight'],
            'school_class_id' => $assignment->school_class_id, 'subject_id' => $assignment->subject_id, 'term_id' => $assignment->term_id]);

        return redirect()->route($request->user()->role.'.gradebooks.show', $assignment, 303)->with('success', 'Dodano pustą kolumnę ocen.');
    }

    public function destroy(DeleteAssessmentRequest $request, Assessment $assessment, AssessmentService $service): RedirectResponse
    {
        // Resolve a permitted return context on the server, never from a hidden assignment ID.
        $assignment = TeacherAssignment::visibleTo($request->user())->forAssessment($assessment)->orderBy('id')->firstOrFail();
        $service->delete($request->user()->id, $assessment->id);

        return redirect()->route($request->user()->role.'.gradebooks.show', $assignment, 303)->with('success', 'Usunięto pustą kolumnę ocen.');
    }
}
