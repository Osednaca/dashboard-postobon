<?php

use App\Http\Controllers\Api\Mobile\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('mobile/v1')->name('mobile.')->group(function (): void {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1,mobile-login')->name('auth.login');
    Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:3,1,mobile-forgot')->name('auth.forgot-password');

    Route::middleware(['auth:sanctum', 'mobile.access'])->group(function (): void {
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        if (is_file(__DIR__.'/mobile_onboarding.php')) {
            require __DIR__.'/mobile_onboarding.php';
        }
    });
});
