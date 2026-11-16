<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\DashboardFilterRequest;
use App\Services\Statistics\AdminDashboard;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(DashboardFilterRequest $request, AdminDashboard $dashboard): Response
    {
        $termId = $request->validated('term_id');

        return Inertia::render('admin/dashboard', $dashboard->props($request->user(), $termId === null ? null : (int) $termId));
    }
}
