<?php

namespace App\Http\Controllers;

use App\Http\Requests\Teacher\GradebookFilterRequest;
use App\Models\StudentEnrollment;
use App\Models\TeacherAssignment;
use App\Services\Grades\RosterStudent;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

class StudentDetailController extends Controller
{
    public function show(GradebookFilterRequest $request, TeacherAssignment $assignment, StudentEnrollment $enrollment, RosterStudent $student): Response|RedirectResponse
    {
        return $student->render($request->user(), $assignment, $enrollment->id, $request->validated());
    }
}
