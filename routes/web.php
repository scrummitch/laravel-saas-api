<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\IntegrationOAuthController;
use App\Http\Controllers\Web\GenerateCsrfCookieController;
use App\Http\Controllers\Web\GetMediaAssetController;
use Illuminate\Support\Facades\Route;

Route::get('/v1/sanctum/csrf-cookie', GenerateCsrfCookieController::class);

Route::prefix('/v1/auth')->group(function () {
    Route::post('/register', [RegisteredUserController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('register');

    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('login');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
        ->middleware('auth')
        ->name('logout');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/integrations/{provider}/callback', [IntegrationOAuthController::class, 'callback']);
});

Route::get('assets/{asset}/{transformation}.{extension}', GetMediaAssetController::class)->name('assets.show');

Route::get('/verify-email/{id}/{hash}', VerifyEmailController::class)
    ->middleware(['auth', 'signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::fallback(function () {
    return response()->json([
        'message' => 'Not Found',
    ], 404);
});
