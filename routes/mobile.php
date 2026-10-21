<?php

use App\Http\Controllers\Api\Mobile\AuthController;
use App\Http\Controllers\Api\Mobile\BusinessTypeController;
use App\Http\Controllers\Api\Mobile\DeviceController;
use App\Http\Controllers\Api\Mobile\EstablishmentController;
use Illuminate\Support\Facades\Route;

Route::prefix('mobile/v1')->name('mobile.')->group(function (): void {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1,mobile-login')->name('auth.login');
    Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:3,1,mobile-forgot')->name('auth.forgot-password');

    Route::middleware(['auth:sanctum', 'mobile.access'])->group(function (): void {
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        Route::get('devices', [DeviceController::class, 'index'])->middleware('mobile.ability:devices:read')->name('devices.index');
        Route::get('establishments/{establishment}/devices', [DeviceController::class, 'establishment'])
            ->middleware('mobile.ability:devices:read')->name('establishments.devices');
        Route::apiResource('establishments', EstablishmentController::class)->middleware('mobile.ability:establishments:manage');
        Route::apiResource('business-types', BusinessTypeController::class)->except('show')->middleware('mobile.ability:business-types:manage');

        if (is_file(__DIR__.'/mobile_onboarding.php')) {
            require __DIR__.'/mobile_onboarding.php';
        }
    });
});
