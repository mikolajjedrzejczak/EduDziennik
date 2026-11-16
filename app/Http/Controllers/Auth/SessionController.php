<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Auth\LoginService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class SessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('auth/login');
    }

    public function store(LoginRequest $request, LoginService $login): RedirectResponse
    {
        $login->authenticate($request->validated('email'), $request->validated('password'), $request->ip() ?? '');
        $request->session()->regenerate();
        $request->session()->forget('url.intended');
        $request->session()->put('password_hash_web', Auth::guard('web')->hashPasswordForCookie($request->user()->getAuthPassword()));

        return to_route('dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('login');
    }
}
