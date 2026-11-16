<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\DashboardFilterRequest;
use App\Services\Statistics\StudentDashboard;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(DashboardFilterRequest $request, StudentDashboard $dashboard): Response
    {
        $termId = $request->validated('term_id');

        return Inertia::render('student/dashboard', $dashboard->props($request->user(), $termId === null ? null : (int) $termId));
    }
}
