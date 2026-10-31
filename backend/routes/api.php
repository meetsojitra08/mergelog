<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChangeEntryController;
use App\Http\Controllers\GitHubWebhookController;
use App\Http\Controllers\RepositoryController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/github/{repository}', GitHubWebhookController::class)
    ->whereNumber('repository')
    ->name('webhooks.github');

Route::post('/register', [AuthController::class, 'register'])->name('register');
Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:login')
    ->name('login');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/me', [AuthController::class, 'me'])->name('me');

    Route::post('/change-entries/{changeEntry}/retry', [ChangeEntryController::class, 'retry'])
        ->whereNumber('changeEntry')
        ->name('change-entries.retry');

    Route::get('/repositories', [RepositoryController::class, 'index'])->name('repositories.index');
    Route::post('/repositories', [RepositoryController::class, 'store'])->name('repositories.store');
    Route::get('/repositories/{repository}', [RepositoryController::class, 'show'])->whereNumber('repository')->name('repositories.show');
    Route::delete('/repositories/{repository}', [RepositoryController::class, 'destroy'])->whereNumber('repository')->name('repositories.destroy');
});
