<?php

namespace App\Console\Commands;

use App\Models\Media;
use App\Models\Schedule;
use App\Services\Z2\Z2DeviceService;
use App\Services\Z2\Z2PlaylistService;
use App\Services\Z2\Z2CampaignSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ProcessSchedules extends Command
{
    protected $signature = 'schedules:process';

    protected $description = 'Execute pending schedules';

    public function __construct(
        private readonly Z2DeviceService $z2DeviceService,
        private readonly Z2PlaylistService $z2PlaylistService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Processing pending schedules...');

        $now = Carbon::now();

        $pendingSchedules = Schedule::with(['device', 'group', 'group.devices', 'campaign'])
            ->where('status', 'pending')
            ->where('scheduled_at', '<=', $now)
            ->get();

        if ($pendingSchedules->isEmpty()) {
            $this->info('No pending schedules to process.');

            return self::SUCCESS;
        }

        $processed = 0;
        $failed = 0;

        foreach ($pendingSchedules as $schedule) {
            try {
                $this->info("Processing schedule: {$schedule->name} (ID: {$schedule->id}, Type: {$schedule->type})");

                $success = match ($schedule->type) {
                    'power_on' => $this->executePowerOn($schedule),
                    'power_off' => $this->executePowerOff($schedule),
                    'change_content' => $this->executeChangeContent($schedule),
                    'activate_campaign' => $this->executeActivateCampaign($schedule),
                    default => $this->handleUnknownType($schedule),
                };

                if ($success) {
                    $schedule->status = 'executed';
                    $schedule->executed_at = $now;
                    $schedule->save();
                    $processed++;
                    $this->info("  ✓ Schedule {$schedule->id} executed successfully");
                } else {
                    $schedule->status = 'failed';
                    $schedule->executed_at = $now;
                    $schedule->save();
                    $failed++;
                    $this->error("  ✗ Schedule {$schedule->id} execution failed (Z2 API returned failure)");
                }
            } catch (\Exception $e) {
                $this->error("Failed to process schedule {$schedule->id}: " . $e->getMessage());
                Log::error('Schedule processing failed', [
                    'schedule_id' => $schedule->id,
                    'type' => $schedule->type,
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                $schedule->status = 'failed';
                $schedule->executed_at = $now;
                $schedule->save();

                $failed++;
            }
        }

        $this->info("Schedule processing completed. Processed: {$processed}, Failed: {$failed}");
        Log::info('Schedule processing completed', ['processed' => $processed, 'failed' => $failed]);

        return self::SUCCESS;
    }

    /**
     * Execute power on via Z2 API.
     */
    private function executePowerOn(Schedule $schedule): bool
    {
        $success = true;

        if ($schedule->device) {
            $result = $this->z2DeviceService->powerOn($schedule->device->mac_address);
            if ($result) {
                $this->info("  Power ON device: {$schedule->device->name} ({$schedule->device->mac_address})");
            } else {
                $this->error("  Failed to power ON device: {$schedule->device->name}");
                $success = false;
            }
        }

        if ($schedule->group && $schedule->group->devices->isNotEmpty()) {
            $groupSuccess = true;
            foreach ($schedule->group->devices as $device) {
                $result = $this->z2DeviceService->powerOn($device->mac_address);
                if (!$result) {
                    $this->error("  Failed to power ON device in group: {$device->name}");
                    $groupSuccess = false;
                }
            }
            $this->info("  Power ON group: {$schedule->group->name} ({$schedule->group->devices->count()} devices)");
            if (!$groupSuccess) {
                $success = false;
            }
        }

        if (!$schedule->device && !$schedule->group) {
            $this->warn("  Schedule {$schedule->id} has no device or group assigned");
            return false;
        }

        return $success;
    }

    /**
     * Execute power off via Z2 API.
     */
    private function executePowerOff(Schedule $schedule): bool
    {
        $success = true;

        if ($schedule->device) {
            $result = $this->z2DeviceService->powerOff($schedule->device->mac_address);
            if ($result) {
                $this->info("  Power OFF device: {$schedule->device->name} ({$schedule->device->mac_address})");
            } else {
                $this->error("  Failed to power OFF device: {$schedule->device->name}");
                $success = false;
            }
        }

        if ($schedule->group && $schedule->group->devices->isNotEmpty()) {
            $groupSuccess = true;
            foreach ($schedule->group->devices as $device) {
                $result = $this->z2DeviceService->powerOff($device->mac_address);
                if (!$result) {
                    $this->error("  Failed to power OFF device in group: {$device->name}");
                    $groupSuccess = false;
                }
            }
            $this->info("  Power OFF group: {$schedule->group->name} ({$schedule->group->devices->count()} devices)");
            if (!$groupSuccess) {
                $success = false;
            }
        }

        if (!$schedule->device && !$schedule->group) {
            $this->warn("  Schedule {$schedule->id} has no device or group assigned");
            return false;
        }

        return $success;
    }

    /**
     * Execute content change via Z2 API.
     */
    private function executeChangeContent(Schedule $schedule): bool
    {
        $media = $schedule->content_id ? Media::find($schedule->content_id) : null;

        if (!$media) {
            $this->error("  Schedule {$schedule->id}: No content (media) assigned for change_content type");
            return false;
        }

        $uiCode = $media->file_path;
        $success = true;

        if ($schedule->device) {
            $result = $this->z2DeviceService->changeVideo($schedule->device->mac_address, $uiCode);
            if ($result) {
                $this->info("  Changed content on device: {$schedule->device->name} to {$media->name}");
            } else {
                $this->error("  Failed to change content on device: {$schedule->device->name}");
                $success = false;
            }
        }

        if ($schedule->group && $schedule->group->devices->isNotEmpty()) {
            $result = $this->z2PlaylistService->assignVideoToGroup($schedule->group->id, $uiCode);
            if ($result) {
                $this->info("  Changed content on group: {$schedule->group->name} to {$media->name}");
            } else {
                $this->error("  Failed to change content on some devices in group: {$schedule->group->name}");
                $success = false;
            }
        }

        if (!$schedule->device && !$schedule->group) {
            $this->warn("  Schedule {$schedule->id} has no device or group assigned");
            return false;
        }

        return $success;
    }

    /**
     * Execute campaign activation.
     */
    private function executeActivateCampaign(Schedule $schedule): bool
    {
        if (!$schedule->campaign) {
            $this->error("  Schedule {$schedule->id}: No campaign assigned for activate_campaign type");
            return false;
        }

        // If Z2CampaignSyncService is available, activate the campaign through Z2
        try {
            $syncService = app(Z2CampaignSyncService::class);
            $syncService->activate($schedule->campaign);
            $this->info("  Activated campaign: {$schedule->campaign->name}");
        } catch (\Exception $e) {
            // Fall back to local activation
            $schedule->campaign->status = 'active';
            $schedule->campaign->save();
            Log::warning('Campaign activated locally but Z2 sync failed', [
                'campaign_id' => $schedule->campaign->id,
                'error' => $e->getMessage(),
            ]);
            $this->warn("  Campaign activated locally but Z2 sync failed: {$e->getMessage()}");
        }

        return true;
    }

    /**
     * Handle unknown schedule type.
     */
    private function handleUnknownType(Schedule $schedule): bool
    {
        $this->warn("Unknown schedule type: {$schedule->type}");
        Log::warning('Unknown schedule type encountered', [
            'schedule_id' => $schedule->id,
            'type' => $schedule->type,
        ]);
        return false;
    }
}
