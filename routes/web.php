<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\LocationController;
use App\Http\Controllers\Web\DeviceController;
use App\Http\Controllers\Web\GroupController;
use App\Http\Controllers\Web\MediaController;
use App\Http\Controllers\Web\CampaignController;
use App\Http\Controllers\Web\ScheduleController;
use App\Http\Controllers\Web\AnalyticsController;
use App\Http\Controllers\Web\SubscriptionController;
use App\Http\Controllers\Web\NotificationController;
use App\Http\Controllers\Web\AuditController;
use App\Http\Controllers\Web\UserController;
use App\Http\Controllers\Web\InstantPlayController;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');

    Route::resource('locations', LocationController::class);

    Route::resource('devices', DeviceController::class);
    Route::post('devices/{device}/power-on', [DeviceController::class, 'powerOn'])->name('devices.power-on');
    Route::post('devices/{device}/power-off', [DeviceController::class, 'powerOff'])->name('devices.power-off');
    Route::post('devices/{device}/disable', [DeviceController::class, 'disable'])->name('devices.disable');
    Route::post('devices/{device}/enable', [DeviceController::class, 'enable'])->name('devices.enable');
    Route::post('devices/{device}/change-group', [DeviceController::class, 'changeGroup'])->name('devices.change-group');
    Route::post('devices/{device}/change-location', [DeviceController::class, 'changeLocation'])->name('devices.change-location');
    Route::post('devices/{device}/assign-content', [DeviceController::class, 'assignContent'])->name('devices.assign-content');
    Route::post('devices/{device}/unbind', [DeviceController::class, 'unbind'])->name('devices.unbind');
    Route::post('devices/{device}/assign-media', [DeviceController::class, 'assignMedia'])->name('devices.assign-media');
    Route::post('devices/{device}/remove-media', [DeviceController::class, 'removeMedia'])->name('devices.remove-media');

    Route::resource('groups', GroupController::class);
    Route::post('groups/{group}/power-on', [GroupController::class, 'powerOnGroup'])->name('groups.power-on');
    Route::post('groups/{group}/power-off', [GroupController::class, 'powerOffGroup'])->name('groups.power-off');
    Route::post('groups/{group}/change-content', [GroupController::class, 'changeContent'])->name('groups.change-content');
    Route::post('groups/{group}/publish-campaign', [GroupController::class, 'publishCampaign'])->name('groups.publish-campaign');

    Route::resource('media', MediaController::class)->parameters(['media' => 'media']);

    Route::resource('campaigns', CampaignController::class);
    Route::post('campaigns/{campaign}/activate', [CampaignController::class, 'activate'])->name('campaigns.activate');
    Route::post('campaigns/{campaign}/pause', [CampaignController::class, 'pause'])->name('campaigns.pause');
    Route::post('campaigns/{campaign}/finish', [CampaignController::class, 'finish'])->name('campaigns.finish');
    Route::post('campaigns/{campaign}/schedule', [CampaignController::class, 'schedule'])->name('campaigns.schedule');
    Route::post('campaigns/{campaign}/add-media', [CampaignController::class, 'addMedia'])->name('campaigns.add-media');
    Route::post('campaigns/{campaign}/remove-media/{media}', [CampaignController::class, 'removeMedia'])->name('campaigns.remove-media');

    Route::resource('schedules', ScheduleController::class);
    Route::post('schedules/{schedule}/execute', [ScheduleController::class, 'execute'])->name('schedules.execute');
    Route::resource('subscriptions', SubscriptionController::class);
    Route::resource('notifications', NotificationController::class);
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');
    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');
    Route::get('/analytics/devices', [AnalyticsController::class, 'devices'])->name('analytics.devices');
    Route::get('/analytics/campaigns', [AnalyticsController::class, 'campaigns'])->name('analytics.campaigns');
    Route::get('/analytics/groups', [AnalyticsController::class, 'groups'])->name('analytics.groups');
    Route::get('/analytics/cities', [AnalyticsController::class, 'cities'])->name('analytics.cities');

    Route::resource('users', UserController::class);

    Route::get('/instant-play', [InstantPlayController::class, 'index'])->name('instant-play.index');
    Route::post('/instant-play/play', [InstantPlayController::class, 'play'])->name('instant-play.play');
    Route::post('/instant-play/play-campaign', [InstantPlayController::class, 'playCampaign'])->name('instant-play.play-campaign');
    Route::post('/instant-play/play-bulk', [InstantPlayController::class, 'playBulk'])->name('instant-play.play-bulk');
});
