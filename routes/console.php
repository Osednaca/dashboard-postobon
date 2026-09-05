<?php

use Illuminate\Support\Facades\Schedule;

// Device status sync every minute
Schedule::job(new \App\Jobs\DeviceStatusJob())->everyMinute();

// Group sync every 5 minutes
Schedule::job(new \App\Jobs\SyncGroupsJob())->everyFiveMinutes();

// Video sync every 15 minutes
Schedule::job(new \App\Jobs\SyncVideosJob())->everyFifteenMinutes();

// Full device sync every 10 minutes
Schedule::job(new \App\Jobs\SyncDevicesJob())->everyTenMinutes();

// Heartbeat every 5 minutes to keep session alive
Schedule::job(new \App\Jobs\HeartbeatJob())->everyFiveMinutes();

// Analytics generation daily
Schedule::command('analytics:generate')->dailyAt('00:00');

// Subscription check daily
Schedule::command('subscriptions:check')->dailyAt('08:00');

// Process scheduled tasks every minute
Schedule::command('schedules:process')->everyMinute()->withoutOverlapping();

// Check offline devices every 10 minutes
Schedule::command('devices:check-offline')->everyTenMinutes();

// Keep only recent upload progress/results; temporary MP4 files are deleted by the job.
Schedule::call(fn () => \App\Models\FleetUpload::where('created_at', '<', now()->subDays(7))->delete())
    ->name('fleet-upload-history:prune')
    ->dailyAt('03:30')
    ->withoutOverlapping();

Schedule::call(fn () => \App\Models\FleetOperation::where('created_at', '<', now()->subDays(7))->delete())
    ->name('fleet-operation-history:prune')
    ->dailyAt('03:35')
    ->withoutOverlapping();
