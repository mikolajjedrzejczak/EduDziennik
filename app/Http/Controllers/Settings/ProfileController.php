<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateProfileRequest;
use App\Services\Users\ProfileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        Gate::authorize('viewProfile', $request->user());

        return Inertia::render('settings/profile', [
            'passwordRateLimited' => $request->routeIs('profile.password.update'),
            'user' => [
                'id' => (string) $request->user()->id,
                'firstName' => $request->user()->first_name,
                'lastName' => $request->user()->last_name,
                'name' => $request->user()->name,
                'email' => $request->user()->email,
                'role' => $request->user()->role,
            ],
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        app(ProfileService::class)->update($request->user()->id, [
            'first_name' => $request->validated('first_name'),
            'last_name' => $request->validated('last_name'),
        ]);

        $request->user()->refresh();

        return to_route('profile.edit')->with('success', 'Dane profilu zostały zapisane.');
    }
}
