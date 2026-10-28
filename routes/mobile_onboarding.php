<?php

use App\Http\Controllers\Api\Mobile\DeviceOnboardingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['mobile.ability:onboarding:create', 'throttle:60,1,mobile-onboarding'])->group(function (): void {
    Route::post('device-onboardings', [DeviceOnboardingController::class, 'store'])->name('onboardings.store');
    Route::get('device-onboardings/{onboarding}', [DeviceOnboardingController::class, 'show'])->name('onboardings.show');
    Route::patch('device-onboardings/{onboarding}', [DeviceOnboardingController::class, 'update'])->name('onboardings.update');
    Route::post('device-onboardings/{onboarding}/events', [DeviceOnboardingController::class, 'event'])->name('onboardings.events');
    Route::post('device-onboardings/{onboarding}/register', [DeviceOnboardingController::class, 'register'])->name('onboardings.register');
    Route::post('device-onboardings/{onboarding}/resume-verification', [DeviceOnboardingController::class, 'resumeVerification'])->name('onboardings.resume-verification');
    Route::post('device-onboardings/{onboarding}/complete', [DeviceOnboardingController::class, 'complete'])->name('onboardings.complete');
    Route::post('device-onboardings/{onboarding}/cancel', [DeviceOnboardingController::class, 'cancel'])->name('onboardings.cancel');
});
