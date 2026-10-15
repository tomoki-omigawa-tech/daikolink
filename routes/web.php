<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ShiftBoardController;
use App\Http\Controllers\Staff\DailyShiftController;
use App\Http\Controllers\Staff\SubjectController;
use App\Http\Controllers\Staff\TermController;
use App\Http\Controllers\Staff\UserController as StaffUserController;
use App\Http\Controllers\SubstituteRequestController;
use App\Http\Controllers\TimetableController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::get('/dashboard', DashboardController::class)->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/timetable', [TimetableController::class, 'show'])
    ->middleware(['auth', 'verified'])
    ->name('timetable');

Route::get('/shifts', [ShiftBoardController::class, 'show'])
    ->middleware(['auth', 'verified'])
    ->name('shifts.board');

Route::middleware('auth')->group(function () {
    Route::get('/requests', [SubstituteRequestController::class, 'index'])->name('requests.index');
    Route::get('/requests/create', [SubstituteRequestController::class, 'create'])->name('requests.create');
    Route::post('/requests', [SubstituteRequestController::class, 'store'])->name('requests.store');
    Route::get('/requests/{substituteRequest}', [SubstituteRequestController::class, 'show'])->name('requests.show');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/subjects', [ProfileController::class, 'updateSubjects'])->name('profile.subjects');
});

Route::middleware(['auth', 'staff'])->prefix('staff')->name('staff.')->group(function () {
    Route::resource('users', StaffUserController::class)->only(['index', 'create', 'store', 'edit', 'update']);
    Route::resource('subjects', SubjectController::class)->only(['index', 'store', 'update']);
    Route::get('terms', [TermController::class, 'index'])->name('terms.index');
    Route::post('terms', [TermController::class, 'store'])->name('terms.store');
    Route::get('terms/{term}/timetable', [TermController::class, 'timetable'])->name('terms.timetable');
    Route::put('terms/{term}/cell', [TermController::class, 'updateCell'])->name('terms.cell');
    Route::post('terms/{term}/apply', [TermController::class, 'apply'])->name('terms.apply');
    Route::get('shifts', [DailyShiftController::class, 'index'])->name('shifts.index');
    Route::put('shifts/cell', [DailyShiftController::class, 'updateCell'])->name('shifts.cell');
    Route::post('shifts/close', [DailyShiftController::class, 'close'])->name('shifts.close');
    Route::delete('shifts/close', [DailyShiftController::class, 'reopen'])->name('shifts.reopen');
    Route::post('shifts/clear', [DailyShiftController::class, 'clear'])->name('shifts.clear');
});

require __DIR__.'/auth.php';
