<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\Notification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CheckOfflineDevices extends Command
{
    protected $signature = 'devices:check-offline';

    protected $description = 'Detect offline devices based on last heartbeat';

    public function handle(): int
    {
        $this->info('Checking for offline devices...');

        $thresholdMinutes = config('devices.offline_threshold_minutes', 15);
        $threshold = Carbon::now()->subMinutes($thresholdMinutes);

        $offlineDevices = Device::where('status', '!=', 'offline')
            ->where(function ($query) use ($threshold) {
                $query->whereNull('last_heartbeat_at')
                    ->orWhere('last_heartbeat_at', '<', $threshold);
            })
            ->get();

        $count = 0;
        foreach ($offlineDevices as $device) {
            $device->status = 'offline';
            $device->power_status = 'off';
            $device->save();

            $minutesSinceHeartbeat = $device->last_heartbeat_at
                ? $device->last_heartbeat_at->diffInMinutes(now())
                : 'never';

            $this->warn("Device {$device->name} ({$device->mac_address}) marked offline. Last heartbeat: {$minutesSinceHeartbeat} minutes ago");

            $adminUsers = \App\Models\User::where('role', 'admin')->get();
            foreach ($adminUsers as $admin) {
                Notification::create([
                    'user_id' => $admin->id,
                    'type' => 'system',
                    'title' => 'Device Offline',
                    'message' => "Device {$device->name} has been offline for {$minutesSinceHeartbeat} minutes.",
                    'data' => [
                        'device_id' => $device->id,
                        'mac_address' => $device->mac_address,
                        'last_heartbeat_at' => $device->last_heartbeat_at?->toIso8601String(),
                    ],
                ]);
            }

            $count++;
        }

        $this->info("{$count} devices marked as offline.");
        Log::info('Offline device check completed', ['offline_count' => $count, 'threshold_minutes' => $thresholdMinutes]);

        $recentlyOnline = Device::where('status', 'offline')
            ->whereNotNull('last_heartbeat_at')
            ->where('last_heartbeat_at', '>=', $threshold)
            ->get();

        $onlineCount = 0;
        foreach ($recentlyOnline as $device) {
            $device->status = 'online';
            $device->power_status = 'on';
            $device->save();
            $this->info("Device {$device->name} back online.");
            $onlineCount++;
        }

        if ($onlineCount > 0) {
            $this->info("{$onlineCount} devices restored to online status.");
            Log::info('Devices restored to online', ['count' => $onlineCount]);
        }

        return self::SUCCESS;
    }
}
