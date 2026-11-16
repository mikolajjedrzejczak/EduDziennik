<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\HistoryFilterRequest;
use App\Models\Grade;
use App\Services\Grades\GradeHistory;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

class GradeHistoryController extends Controller
{
    public function index(HistoryFilterRequest $request, GradeHistory $history): Response|RedirectResponse
    {
        return $history->render($request->user(), $request->filters(), null, $request->url());
    }

    public function show(HistoryFilterRequest $request, Grade $grade, GradeHistory $history): Response|RedirectResponse
    {
        return $history->render($request->user(), $request->filters(), $grade, $request->url());
    }
}
