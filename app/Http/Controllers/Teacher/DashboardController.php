<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\DashboardFilterRequest;
use App\Services\Statistics\TeacherDashboard;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(DashboardFilterRequest $request, TeacherDashboard $dashboard): Response
    {
        $termId = $request->validated('term_id');

        return Inertia::render('teacher/dashboard', $dashboard->props($request->user(), $termId === null ? null : (int) $termId));
    }
}
