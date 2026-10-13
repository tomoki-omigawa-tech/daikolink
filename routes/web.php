<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Staff\TermController;
use App\Http\Controllers\Staff\UserController as StaffUserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'staff'])->prefix('staff')->name('staff.')->group(function () {
    Route::resource('users', StaffUserController::class)->only(['index', 'create', 'store']);
    Route::get('terms', [TermController::class, 'index'])->name('terms.index');
    Route::post('terms', [TermController::class, 'store'])->name('terms.store');
    Route::get('terms/{term}/timetable', [TermController::class, 'timetable'])->name('terms.timetable');
});

require __DIR__.'/auth.php';
