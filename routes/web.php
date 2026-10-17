<?php

use App\Http\Controllers\Web\AnalyticsController;
use App\Http\Controllers\Web\AuditController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\BusinessTypeController;
use App\Http\Controllers\Web\CampaignController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\DeviceController;
use App\Http\Controllers\Web\DeviceEstablishmentController;
use App\Http\Controllers\Web\DevicePreviewController;
use App\Http\Controllers\Web\DeviceVideoRemovalController;
use App\Http\Controllers\Web\EstablishmentController;
use App\Http\Controllers\Web\GroupController;
use App\Http\Controllers\Web\InstantPlayController;
use App\Http\Controllers\Web\MediaContentController;
use App\Http\Controllers\Web\MediaController;
use App\Http\Controllers\Web\NotificationController;
use App\Http\Controllers\Web\ScheduleController;
use App\Http\Controllers\Web\UnifiedFleetController;
use App\Http\Controllers\Web\UserController;
use App\Http\Controllers\Web\Wl35DeviceController;
use Illuminate\Support\Facades\Route;

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

Route::middleware(['auth', 'role:superadmin,admin,tecnico,campanas'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index')->middleware('role:superadmin,admin,campanas');
    Route::get('/device-previews', DevicePreviewController::class)->name('devices.previews')->middleware('role:superadmin,tecnico,campanas');
    Route::get('/media/{media}/content', MediaContentController::class)->name('media.content')->middleware('role:superadmin,admin,campanas');

    Route::redirect('/fleet', '/devices')->name('fleet.index')->middleware('role:superadmin,tecnico,campanas');
    Route::post('/fleet/commands', [UnifiedFleetController::class, 'command'])->name('fleet.command')->middleware('role:superadmin');
    Route::post('/fleet/uploads', [UnifiedFleetController::class, 'upload'])->name('fleet.upload')->middleware('role:superadmin');
    Route::get('/fleet/uploads/{fleetUpload}', [UnifiedFleetController::class, 'uploadStatus'])->name('fleet.upload.status')->middleware('role:superadmin,admin,campanas');
    Route::get('/fleet/operations/{fleetOperation}', [UnifiedFleetController::class, 'operationStatus'])->name('fleet.operation.status')->middleware('role:superadmin');

    Route::resource('business-types', BusinessTypeController::class)->only(['index', 'store', 'update', 'destroy'])->middleware('role:superadmin,tecnico');
    Route::resource('establishments', EstablishmentController::class)->middleware('role:superadmin,tecnico');

    Route::patch('devices/{device}/establishment', [DeviceEstablishmentController::class, 'assign'])
        ->name('devices.assign-establishment')->middleware('role:superadmin,tecnico');
    Route::patch('devices/wl35/{deviceId}/establishment', [DeviceEstablishmentController::class, 'assignWl35'])
        ->where('deviceId', '[A-Za-z0-9_.:-]+')
        ->name('devices.wl35.assign-establishment')->middleware('role:superadmin,tecnico');

    Route::get('devices/wl35/{deviceId}', [Wl35DeviceController::class, 'show'])
        ->where('deviceId', '[A-Za-z0-9_.:-]+')
        ->name('devices.wl35.show')->middleware('role:superadmin,tecnico,campanas');
    Route::patch('devices/wl35/{deviceId}', [Wl35DeviceController::class, 'update'])
        ->where('deviceId', '[A-Za-z0-9_.:-]+')
        ->name('devices.wl35.update')->middleware('role:superadmin');
    Route::delete('devices/wl35/{deviceId}/videos', [Wl35DeviceController::class, 'deleteVideo'])
        ->where('deviceId', '[A-Za-z0-9_.:-]+')
        ->name('devices.wl35.videos.destroy')->middleware('role:superadmin');
    Route::post('devices/wl35/{deviceId}/volume', [Wl35DeviceController::class, 'setVolume'])
        ->where('deviceId', '[A-Za-z0-9_.:-]+')
        ->name('devices.wl35.volume')->middleware('role:superadmin');

    Route::resource('devices', DeviceController::class)->except(['index', 'show'])->middleware('role:superadmin');
    Route::resource('devices', DeviceController::class)->only(['index', 'show'])->middleware('role:superadmin,tecnico,campanas');
    Route::post('devices/{device}/power-on', [DeviceController::class, 'powerOn'])->name('devices.power-on')->middleware('role:superadmin');
    Route::post('devices/{device}/power-off', [DeviceController::class, 'powerOff'])->name('devices.power-off')->middleware('role:superadmin');
    Route::post('devices/{device}/bluetooth-on', [DeviceController::class, 'bluetoothOn'])->name('devices.bluetooth-on')->middleware('role:superadmin');
    Route::post('devices/{device}/bluetooth-off', [DeviceController::class, 'bluetoothOff'])->name('devices.bluetooth-off')->middleware('role:superadmin');
    Route::post('devices/{device}/disable', [DeviceController::class, 'disable'])->name('devices.disable')->middleware('role:superadmin');
    Route::post('devices/{device}/enable', [DeviceController::class, 'enable'])->name('devices.enable')->middleware('role:superadmin');
    Route::post('devices/{device}/change-group', [DeviceController::class, 'changeGroup'])->name('devices.change-group')->middleware('role:superadmin');
    Route::post('devices/{device}/assign-content', [DeviceController::class, 'assignContent'])->name('devices.assign-content')->middleware('role:superadmin');
    Route::post('devices/{device}/unbind', [DeviceController::class, 'unbind'])->name('devices.unbind')->middleware('role:superadmin');
    Route::post('devices/{device}/assign-media', [DeviceController::class, 'assignMedia'])->name('devices.assign-media')->middleware('role:superadmin');
    Route::post('devices/{device}/remove-media', [DeviceController::class, 'removeMedia'])->name('devices.remove-media')->middleware('role:superadmin');
    Route::post('devices/bulk-operation', [DeviceController::class, 'bulkOperation'])->name('devices.bulk-operation')->middleware('role:superadmin');
    Route::post('devices/bulk-format-sd', [DeviceController::class, 'bulkFormatSd'])->name('devices.bulk-format-sd')->middleware('role:superadmin');
    Route::post('devices/bulk-assign-media', [DeviceController::class, 'bulkAssignMedia'])->name('devices.bulk-assign-media')->middleware('role:superadmin');
    Route::post('devices/bulk-remove-video', DeviceVideoRemovalController::class)->name('devices.bulk-remove-video')->middleware('role:superadmin');
    Route::post('devices/{device}/format-sd', [DeviceController::class, 'formatSd'])->name('devices.format-sd')->middleware('role:superadmin');
    Route::post('devices/{device}/set-volume', [DeviceController::class, 'setVolume'])->name('devices.set-volume')->middleware('role:superadmin');

    Route::resource('groups', GroupController::class)->only(['create', 'store'])->middleware('role:superadmin,admin,campanas');
    Route::resource('groups', GroupController::class)->only(['edit', 'update', 'destroy'])->middleware('role:superadmin,admin');
    Route::resource('groups', GroupController::class)->only(['index', 'show'])->middleware('role:superadmin,admin,campanas');
    Route::post('groups/{group}/power-on', [GroupController::class, 'powerOnGroup'])->name('groups.power-on')->middleware('role:superadmin,admin');
    Route::post('groups/{group}/power-off', [GroupController::class, 'powerOffGroup'])->name('groups.power-off')->middleware('role:superadmin,admin');
    Route::post('groups/{group}/change-content', [GroupController::class, 'changeContent'])->name('groups.change-content')->middleware('role:superadmin,admin');
    Route::post('groups/{group}/publish-campaign', [GroupController::class, 'publishCampaign'])->name('groups.publish-campaign')->middleware('role:superadmin,admin');

    Route::post('media/bulk-delete', [MediaController::class, 'bulkDestroy'])->name('media.bulk-delete')->middleware('role:superadmin,admin,campanas');
    Route::resource('media', MediaController::class)->parameters(['media' => 'media'])->middleware('role:superadmin,admin,campanas');

    Route::resource('campaigns', CampaignController::class)->middleware('role:superadmin,admin,campanas');
    Route::post('campaigns/{campaign}/activate', [CampaignController::class, 'activate'])->name('campaigns.activate')->middleware('role:superadmin,admin,campanas');
    Route::post('campaigns/{campaign}/pause', [CampaignController::class, 'pause'])->name('campaigns.pause')->middleware('role:superadmin,admin,campanas');
    Route::post('campaigns/{campaign}/finish', [CampaignController::class, 'finish'])->name('campaigns.finish')->middleware('role:superadmin,admin,campanas');
    Route::post('campaigns/{campaign}/schedule', [CampaignController::class, 'schedule'])->name('campaigns.schedule')->middleware('role:superadmin,admin,campanas');
    Route::post('campaigns/{campaign}/add-media', [CampaignController::class, 'addMedia'])->name('campaigns.add-media')->middleware('role:superadmin,admin,campanas');
    Route::post('campaigns/{campaign}/remove-media/{media}', [CampaignController::class, 'removeMedia'])->name('campaigns.remove-media')->middleware('role:superadmin,admin,campanas');

    Route::resource('schedules', ScheduleController::class)->middleware('role:superadmin,admin,campanas');
    Route::post('schedules/{schedule}/execute', [ScheduleController::class, 'execute'])->name('schedules.execute')->middleware('role:superadmin,admin,campanas');
    Route::resource('notifications', NotificationController::class)->middleware('role:superadmin');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read')->middleware('role:superadmin');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all')->middleware('role:superadmin');
    Route::get('/audit', [AuditController::class, 'index'])->name('audit.index')->middleware('role:superadmin');
    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index')->middleware('role:superadmin');
    Route::get('/analytics/devices', [AnalyticsController::class, 'devices'])->name('analytics.devices')->middleware('role:superadmin');
    Route::get('/analytics/campaigns', [AnalyticsController::class, 'campaigns'])->name('analytics.campaigns')->middleware('role:superadmin');
    Route::get('/analytics/groups', [AnalyticsController::class, 'groups'])->name('analytics.groups')->middleware('role:superadmin');
    Route::get('/analytics/cities', [AnalyticsController::class, 'cities'])->name('analytics.cities')->middleware('role:superadmin');

    Route::resource('users', UserController::class)->middleware('role:superadmin');

    Route::get('/instant-play', [InstantPlayController::class, 'index'])->name('instant-play.index')->middleware('role:superadmin,admin,campanas');
    Route::post('/instant-play/media', [InstantPlayController::class, 'playMedia'])->name('instant-play.media')->middleware('role:superadmin,admin,campanas');
    Route::post('/instant-play/play', [InstantPlayController::class, 'play'])->name('instant-play.play')->middleware('role:superadmin,admin,campanas');
    Route::post('/instant-play/play-campaign', [InstantPlayController::class, 'playCampaign'])->name('instant-play.play-campaign')->middleware('role:superadmin,admin,campanas');
    Route::post('/instant-play/play-bulk', [InstantPlayController::class, 'playBulk'])->name('instant-play.play-bulk')->middleware('role:superadmin,admin,campanas');
});
