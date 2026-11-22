<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ShowsGradeDetail;
use App\Http\Requests\GradeFilterRequest;
use App\Services\Grades\StaffGradeList;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

/** Teacher and admin grade list, detail and editing; data scope comes from the actor. */
class StaffGradeController extends GradeEditingController
{
    use ShowsGradeDetail;

    public function index(GradeFilterRequest $request, StaffGradeList $list): Response|RedirectResponse
    {
        return $list->render($request->user(), $request->validated());
    }
}
