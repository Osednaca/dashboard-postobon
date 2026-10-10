<?php

use App\Jobs\DeviceStatusJob;
use App\Jobs\HeartbeatJob;
use App\Jobs\SyncDevicesJob;
use App\Jobs\SyncGroupsJob;
use App\Jobs\SyncVideosJob;
use App\Models\FleetOperation;
use App\Models\FleetUpload;
use App\Services\Fleet\CompressedMediaPreview;
use Illuminate\Support\Facades\Schedule;

// Device status sync every minute
Schedule::job(new DeviceStatusJob)->everyMinute();

// Group sync every 5 minutes
Schedule::job(new SyncGroupsJob)->everyFiveMinutes();

// Video sync every 15 minutes
Schedule::job(new SyncVideosJob)->everyFifteenMinutes();

// Full device sync every 10 minutes
Schedule::job(new SyncDevicesJob)->everyTenMinutes();

// Heartbeat every 5 minutes to keep session alive
Schedule::job(new HeartbeatJob)->everyFiveMinutes();

// Analytics generation daily
Schedule::command('analytics:generate')->dailyAt('00:00');

// Subscription check daily
Schedule::command('subscriptions:check')->dailyAt('08:00');

// Process scheduled tasks every minute
Schedule::command('schedules:process')->everyMinute()->withoutOverlapping();

// Check offline devices every 10 minutes
Schedule::command('devices:check-offline')->everyTenMinutes();

// Keep only recent upload progress/results; temporary MP4 files are deleted by the job.
Schedule::call(fn () => FleetUpload::where('created_at', '<', now()->subDays(7))->delete())
    ->name('fleet-upload-history:prune')
    ->dailyAt('03:30')
    ->withoutOverlapping();

Schedule::call(fn () => FleetOperation::where('created_at', '<', now()->subDays(7))->delete())
    ->name('fleet-operation-history:prune')
    ->dailyAt('03:35')
    ->withoutOverlapping();

Schedule::call(fn () => app(CompressedMediaPreview::class)->prune())
    ->name('media-preview:prune')->dailyAt('03:40')->withoutOverlapping();
