<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Tests\Fixtures\Forms\ProbeRequest;

// Loaded explicitly by tests, never by the application's route files.
Route::middleware('web')->group(function (): void {
    Route::get('/__tests/forms', fn () => Inertia::render('probe', [
        'success' => session('probe_success', false),
    ]));

    Route::post('/__tests/forms/{context}', function (ProbeRequest $request) {
        session()->put('probe_effect', $request->safe()->except('password'));

        return redirect('/__tests/forms')->with('probe_success', true);
    });
});
