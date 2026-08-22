<?php

use App\Http\Controllers\TrainingSessionController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware('auth')->group(function () {
    Route::inertia('prancheta', 'Board')->name('board');

    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::prefix('api')->name('sessions.')->group(function () {
        Route::get('sessions', [TrainingSessionController::class, 'index'])->name('index');
        Route::get('sessions/{trainingSession}', [TrainingSessionController::class, 'show'])->name('show');
        Route::put('sessions/{trainingSession}', [TrainingSessionController::class, 'update'])->name('update');
        Route::delete('sessions/{trainingSession}', [TrainingSessionController::class, 'destroy'])->name('destroy');
    });
});

require __DIR__.'/settings.php';
