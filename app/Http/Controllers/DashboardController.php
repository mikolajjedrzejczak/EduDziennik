<?php

namespace App\Http\Controllers;

use App\Services\Navigation\NavigationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Sends the signed-in user to their own dashboard; the client never chooses the target. */
class DashboardController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        $route = NavigationService::dashboardRoute($request->user());
        abort_if($route === null, 403);

        return to_route($route);
    }
}
