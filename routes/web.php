<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Staff\TermController;
use App\Http\Controllers\Staff\UserController as StaffUserController;
use App\Http\Controllers\TimetableController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/timetable', [TimetableController::class, 'show'])
    ->middleware(['auth', 'verified'])
    ->name('timetable');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'staff'])->prefix('staff')->name('staff.')->group(function () {
    Route::resource('users', StaffUserController::class)->only(['index', 'create', 'store']);
    Route::get('terms', [TermController::class, 'index'])->name('terms.index');
    Route::post('terms', [TermController::class, 'store'])->name('terms.store');
    Route::get('terms/{term}/timetable', [TermController::class, 'timetable'])->name('terms.timetable');
    Route::put('terms/{term}/cell', [TermController::class, 'updateCell'])->name('terms.cell');
    Route::post('terms/{term}/apply', [TermController::class, 'apply'])->name('terms.apply');
});

require __DIR__.'/auth.php';
