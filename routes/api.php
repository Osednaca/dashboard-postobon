<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\GroupController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\CampaignController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\AuditController;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    Route::get('/dashboard', [DashboardController::class, 'index']);

    Route::apiResource('locations', LocationController::class)->names('api.locations');
    Route::apiResource('devices', DeviceController::class)->names('api.devices');
    Route::apiResource('groups', GroupController::class)->names('api.groups');
    Route::apiResource('media', MediaController::class)->parameters(['media' => 'media'])->names('api.media');
    Route::apiResource('campaigns', CampaignController::class)->names('api.campaigns');
    Route::apiResource('schedules', ScheduleController::class)->names('api.schedules');
    Route::apiResource('subscriptions', SubscriptionController::class)->names('api.subscriptions');
    Route::apiResource('notifications', NotificationController::class)->names('api.notifications');
    Route::apiResource('audit', AuditController::class)->only(['index', 'show'])->names('api.audit');

    Route::get('/analytics', [AnalyticsController::class, 'index']);
    Route::get('/analytics/devices', [AnalyticsController::class, 'devices']);
    Route::get('/analytics/campaigns', [AnalyticsController::class, 'campaigns']);
    Route::get('/analytics/groups', [AnalyticsController::class, 'groups']);
    Route::get('/analytics/cities', [AnalyticsController::class, 'cities']);
});
