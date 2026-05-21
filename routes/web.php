<?php

use App\Http\Controllers\API\Dashboard\DashboardStatsAction;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\IntegrationOAuthController;
use App\Http\Controllers\Web\GenerateCsrfCookieController;
use App\Http\Controllers\Web\GetMediaAssetController;
use Illuminate\Support\Facades\Route;

Route::get('/v1/sanctum/csrf-cookie', GenerateCsrfCookieController::class);


Route::any('/test', function (\Illuminate\Http\Request $request) {
})->withoutMiddleware('web');

Route::get('/dashboard/stats', DashboardStatsAction::class);


Route::get('/cmd/start-demo-job', function () {

    \App\Jobs\ResetDemoAccountJob::dispatch();
    return response()->json([
        'message' => 'Job dispatched',
    ]);
})->name('cmd.start-demo-job');

Route::group([
    'prefix' => '/v1/auth',
], function () {
    Route::post('/register', [RegisteredUserController::class, 'store'])
        ->name('register');

    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->name('login');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
        ->middleware('auth')
        ->name('logout');
});

Route::group([
    'middleware' => 'auth:sanctum',
], function () {
    Route::get('/integrations/{provider}/callback', [IntegrationOAuthController::class, 'callback'])->middleware(['auth:sanctum']);
});

Route::get('assets/{asset}/{transformation}.{extension}', GetMediaAssetController::class)->name('assets.show');

//Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
//    ->middleware('guest')
//    ->name('password.email');

//Route::post('/reset-password', [NewPasswordController::class, 'store'])
//    ->middleware('guest')
//    ->name('password.store');

Route::get('/verify-email/{id}/{hash}', VerifyEmailController::class)
    ->middleware(['auth', 'signed', 'throttle:6,1'])
    ->name('verification.verify');

//Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
//    ->middleware(['auth', 'throttle:6,1'])
//    ->name('verification.send');

Route::fallback(function () {
    return response()->json([
        'message' => 'Not Found',
    ], 404);
});
