<?php

namespace App\Http\Controllers\Concerns;

use App\Http\Requests\GradeDetailRequest;
use App\Models\Grade;
use App\Services\Grades\GradeDetail;
use Inertia\Inertia;
use Inertia\Response;

/** Grade detail page shared by every role; GradeDetailRequest authorizes the record. */
trait ShowsGradeDetail
{
    public function show(GradeDetailRequest $request, Grade $grade, GradeDetail $detail): Response
    {
        return Inertia::render('shared/grades/show', $detail->props($request->user(), $grade, (int) $request->validated('history_page', 1)));
    }
}
