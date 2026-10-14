<?php

use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\AuditController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CampaignController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\GroupController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Web\DeviceEstablishmentController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

Route::middleware(['auth:sanctum', 'role:superadmin,admin,tecnico,campanas'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('role:superadmin,admin,campanas');

    Route::patch('devices/{device}/establishment', [DeviceEstablishmentController::class, 'assign'])
        ->name('api.devices.assign-establishment')->middleware('role:superadmin,tecnico');
    Route::patch('devices/wl35/{deviceId}/establishment', [DeviceEstablishmentController::class, 'assignWl35'])
        ->where('deviceId', '[A-Za-z0-9_.:-]+')
        ->name('api.devices.wl35.assign-establishment')->middleware('role:superadmin,tecnico');

    Route::apiResource('devices', DeviceController::class)->only(['index', 'show'])->names('api.devices')->middleware('role:superadmin,tecnico,campanas');
    Route::apiResource('devices', DeviceController::class)->except(['index', 'show'])->names('api.devices')->middleware('role:superadmin');
    Route::post('devices/bulk-format-sd', [DeviceController::class, 'bulkFormatSd'])->name('api.devices.bulk-format-sd')->middleware('role:superadmin');
    Route::post('devices/{device}/format-sd', [DeviceController::class, 'formatSd'])->name('api.devices.format-sd')->middleware('role:superadmin');
    Route::get('devices/{device}/settings', [DeviceController::class, 'getSettings'])->name('api.devices.settings')->middleware('role:superadmin');
    Route::post('devices/{device}/volume', [DeviceController::class, 'setVolume'])->name('api.devices.set-volume')->middleware('role:superadmin');
    Route::apiResource('groups', GroupController::class)->only(['index', 'show', 'store'])->names('api.groups')->middleware('role:superadmin,admin,campanas');
    Route::apiResource('groups', GroupController::class)->only(['update', 'destroy'])->names('api.groups')->middleware('role:superadmin,admin');
    Route::post('media/bulk-delete', [MediaController::class, 'bulkDestroy'])->name('api.media.bulk-delete')->middleware('role:superadmin,admin,campanas');
    Route::apiResource('media', MediaController::class)->parameters(['media' => 'media'])->names('api.media')->middleware('role:superadmin,admin,campanas');
    Route::apiResource('campaigns', CampaignController::class)->names('api.campaigns')->middleware('role:superadmin,admin,campanas');
    Route::apiResource('schedules', ScheduleController::class)->names('api.schedules')->middleware('role:superadmin,admin,campanas');
    Route::apiResource('notifications', NotificationController::class)->names('api.notifications')->middleware('role:superadmin');
    Route::apiResource('audit', AuditController::class)->only(['index', 'show'])->names('api.audit')->middleware('role:superadmin');

    Route::get('/analytics', [AnalyticsController::class, 'index'])->middleware('role:superadmin');
    Route::get('/analytics/devices', [AnalyticsController::class, 'devices'])->middleware('role:superadmin');
    Route::get('/analytics/campaigns', [AnalyticsController::class, 'campaigns'])->middleware('role:superadmin');
    Route::get('/analytics/groups', [AnalyticsController::class, 'groups'])->middleware('role:superadmin');
    Route::get('/analytics/cities', [AnalyticsController::class, 'cities'])->middleware('role:superadmin');
});
