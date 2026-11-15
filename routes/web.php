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
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('classes', [Admin\SchoolClassController::class, 'index'])->name('classes.index');
        Route::post('classes', [Admin\SchoolClassController::class, 'store'])->name('classes.store');
        Route::patch('classes/{schoolClass}', [Admin\SchoolClassController::class, 'update'])->name('classes.update');
        Route::delete('classes/{schoolClass}', [Admin\SchoolClassController::class, 'destroy'])->name('classes.destroy');
        Route::post('classes/{schoolClass}/enrollments', [Admin\EnrollmentController::class, 'store'])->name('enrollments.store');
        Route::patch('classes/{schoolClass}/enrollments/{enrollment}', [Admin\EnrollmentController::class, 'update'])->name('enrollments.update');
        Route::resource('subjects', Admin\SubjectController::class)->only(['index', 'store', 'update', 'destroy']);
    });
});

require __DIR__.'/settings.php';

require __DIR__.'/auth.php';
