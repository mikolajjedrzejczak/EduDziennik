<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Student;
use App\Http\Controllers\Teacher;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('student', [Student\DashboardController::class, 'index'])->middleware('role:student')->name('student.dashboard');
    Route::get('teacher', [Teacher\DashboardController::class, 'index'])->middleware('role:teacher')->name('teacher.dashboard');
    Route::get('admin', [Admin\DashboardController::class, 'index'])->middleware('role:admin')->name('admin.dashboard');
});

require __DIR__.'/settings.php';

require __DIR__.'/auth.php';
