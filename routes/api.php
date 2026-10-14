<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\GroupController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\CampaignController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\AuditController;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

Route::middleware(['auth:sanctum', 'role:superadmin,admin,tecnico'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    Route::get('/dashboard', [DashboardController::class, 'index']);

    Route::apiResource('devices', DeviceController::class)->names('api.devices');
    Route::post('devices/bulk-format-sd', [DeviceController::class, 'bulkFormatSd'])->name('api.devices.bulk-format-sd');
    Route::post('devices/{device}/format-sd', [DeviceController::class, 'formatSd'])->name('api.devices.format-sd');
    Route::get('devices/{device}/settings', [DeviceController::class, 'getSettings'])->name('api.devices.settings');
    Route::post('devices/{device}/volume', [DeviceController::class, 'setVolume'])->name('api.devices.set-volume');
    Route::apiResource('groups', GroupController::class)->names('api.groups');
    Route::post('media/bulk-delete', [MediaController::class, 'bulkDestroy'])->name('api.media.bulk-delete');
    Route::apiResource('media', MediaController::class)->parameters(['media' => 'media'])->names('api.media');
    Route::apiResource('campaigns', CampaignController::class)->names('api.campaigns')->middleware('role:superadmin,admin');
    Route::apiResource('schedules', ScheduleController::class)->names('api.schedules');
    Route::apiResource('notifications', NotificationController::class)->names('api.notifications');
    Route::apiResource('audit', AuditController::class)->only(['index', 'show'])->names('api.audit')->middleware('role:superadmin,admin');

    Route::get('/analytics', [AnalyticsController::class, 'index'])->middleware('role:superadmin,admin');
    Route::get('/analytics/devices', [AnalyticsController::class, 'devices'])->middleware('role:superadmin,admin');
    Route::get('/analytics/campaigns', [AnalyticsController::class, 'campaigns'])->middleware('role:superadmin,admin');
    Route::get('/analytics/groups', [AnalyticsController::class, 'groups'])->middleware('role:superadmin,admin');
    Route::get('/analytics/cities', [AnalyticsController::class, 'cities'])->middleware('role:superadmin,admin');
});
