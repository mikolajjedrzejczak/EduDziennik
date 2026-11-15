<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/** Empty role dashboard shell (F-007); data panels arrive with the role's dashboard feature. */
class DashboardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('student/dashboard');
    }
}
