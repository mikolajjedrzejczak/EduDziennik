<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\GradeDetailRequest;
use App\Http\Requests\GradeFilterRequest;
use App\Models\Grade;
use App\Services\Grades\GradeDetail;
use App\Services\Grades\StaffGradeList;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class GradeController extends Controller
{
    public function index(GradeFilterRequest $request, StaffGradeList $list): Response|RedirectResponse
    {
        return $list->render($request->user(), $request->validated());
    }

    public function show(GradeDetailRequest $request, Grade $grade, GradeDetail $detail): Response
    {
        return Inertia::render('shared/grades/show', $detail->props($request->user(), $grade, (int) $request->validated('history_page', 1)));
    }
}
