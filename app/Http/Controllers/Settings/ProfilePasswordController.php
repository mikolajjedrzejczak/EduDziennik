<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdatePasswordRequest;
use App\Services\Users\ProfileService;
use Illuminate\Http\RedirectResponse;

class ProfilePasswordController extends Controller
{
    public function update(UpdatePasswordRequest $request, ProfileService $profiles): RedirectResponse
    {
        $profiles->password($request->user()->id, $request->validated('current_password'), $request->validated('password'), $request->session()->getId());
        // AuthenticateSession stores this fresh hash for the renewed current session.
        $request->user()->refresh();
        $request->session()->regenerate();

        return to_route('profile.edit')->with('success', 'Hasło zostało zmienione. Pozostałe sesje zostały wylogowane.');
    }
}
