<?php

use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\ProfilePasswordController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/profile');

    Route::get('settings/profile', fn () => to_route('profile.edit'));

    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::redirect('settings/security', '/profile')->name('security.edit');

    Route::put('profile/password', [ProfilePasswordController::class, 'update'])
        ->middleware('throttle:profile-password')
        ->name('profile.password.update');

    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');
});
