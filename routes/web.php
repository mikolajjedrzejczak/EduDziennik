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
    Route::get('student/grades', [Student\GradeController::class, 'index'])->middleware('role:student')->name('student.grades.index');
    foreach (['student' => Student\GradeController::class, 'teacher' => Teacher\GradeController::class, 'admin' => Admin\GradeController::class] as $role => $controller) {
        Route::get($role.'/grades/{grade}', [$controller, 'show'])->withTrashed()->whereNumber('grade')->middleware('role:'.$role)->name($role.'.grades.show');
    }
    foreach (['student' => Student\GradeHistoryController::class, 'teacher' => Teacher\GradeHistoryController::class, 'admin' => Admin\GradeHistoryController::class] as $role => $controller) {
        Route::get($role.'/history', [$controller, 'index'])->middleware('role:'.$role)->name($role.'.history.index');
        Route::get($role.'/grades/{grade}/history', [$controller, 'show'])->withTrashed()->whereNumber('grade')->middleware('role:'.$role)->name($role.'.grades.history');
    }
    Route::get('teacher', [Teacher\DashboardController::class, 'index'])->middleware('role:teacher')->name('teacher.dashboard');
    Route::get('admin', [Admin\DashboardController::class, 'index'])->middleware('role:admin')->name('admin.dashboard');
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('classes', [Admin\SchoolClassController::class, 'index'])->name('classes.index');
        Route::post('classes', [Admin\SchoolClassController::class, 'store'])->name('classes.store');
        Route::patch('classes/{schoolClass}', [Admin\SchoolClassController::class, 'update'])->name('classes.update');
        Route::delete('classes/{schoolClass}', [Admin\SchoolClassController::class, 'destroy'])->name('classes.destroy');
        Route::post('classes/{schoolClass}/enrollments', [Admin\EnrollmentController::class, 'store'])->name('enrollments.store');
        Route::patch('classes/{schoolClass}/enrollments/{enrollment}', [Admin\EnrollmentController::class, 'update'])->name('enrollments.update');
        Route::resource('assignments', Admin\TeacherAssignmentController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('users', Admin\UserController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'])->withTrashed(['show']);
        Route::resource('subjects', Admin\SubjectController::class)->only(['index', 'store', 'update', 'destroy']);
    });
});

require __DIR__.'/settings.php';

require __DIR__.'/auth.php';
