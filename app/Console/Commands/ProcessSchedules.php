<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\Media;
use App\Models\Schedule;
use App\Services\ScheduleRecurrenceService;
use App\Services\Z2\Z2CampaignSyncService;
use App\Services\Z2\Z2DeviceService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessSchedules extends Command
{
    protected $signature = 'schedules:process';

    protected $description = 'Execute pending one-time and recurring schedules';

    public function __construct(
        private readonly Z2DeviceService $z2DeviceService,
        private readonly ScheduleRecurrenceService $recurrenceService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $now = CarbonImmutable::now(config('app.timezone'));
        $pendingSchedules = Schedule::with(['device', 'group.devices', 'campaign'])
            ->where('status', 'pending')
            ->where('scheduled_at', '<=', $now)
            ->orderBy('scheduled_at')
            ->get();

        if ($pendingSchedules->isEmpty()) {
            return self::SUCCESS;
        }

        $processed = 0;
        $failed = 0;

        foreach ($pendingSchedules as $schedule) {
            try {
                $success = match ($schedule->type) {
                    'power_on' => $this->executeOnTargets(
                        $schedule,
                        fn (Device $device): bool => $this->z2DeviceService->powerOn($device->mac_address),
                    ),
                    'power_off' => $this->executeOnTargets(
                        $schedule,
                        fn (Device $device): bool => $this->z2DeviceService->powerOff($device->mac_address),
                    ),
                    'format_sd' => $this->executeOnTargets(
                        $schedule,
                        fn (Device $device): bool => $this->z2DeviceService->formatSd($device->mac_address),
                    ),
                    'change_content' => $this->executeChangeContent($schedule),
                    'activate_campaign' => $this->executeActivateCampaign($schedule),
                    default => false,
                };

                $this->finishRun($schedule, $success, $now, $success ? null : 'El dispositivo o la nube rechazó la instrucción.');
                if ($success) {
                    $processed++;
                } else {
                    $failed++;
                }
            } catch (Throwable $exception) {
                $this->finishRun($schedule, false, $now, $exception->getMessage());
                $failed++;

                Log::error('Schedule processing failed', [
                    'schedule_id' => $schedule->id,
                    'type' => $schedule->type,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        Log::info('Schedule processing completed', compact('processed', 'failed'));

        return self::SUCCESS;
    }

    private function executeOnTargets(Schedule $schedule, callable $command): bool
    {
        $devices = $this->targetDevices($schedule);

        if ($devices->isEmpty()) {
            Log::warning('Schedule has no eligible target devices', ['schedule_id' => $schedule->id]);

            return false;
        }

        $success = true;

        foreach ($devices as $device) {
            if (! $command($device)) {
                $success = false;
            }
        }

        return $success;
    }

    private function executeChangeContent(Schedule $schedule): bool
    {
        $media = $schedule->content_id ? Media::find($schedule->content_id) : null;
        $devices = $this->targetDevices($schedule);

        if ($media === null || $devices->isEmpty()) {
            return false;
        }

        $result = $this->z2DeviceService->changeVideoOnDevices(
            $devices->pluck('mac_address')->all(),
            $media->file_path,
        );

        return $result['results'] !== []
            && ! in_array(false, $result['results'], true);
    }

    private function executeActivateCampaign(Schedule $schedule): bool
    {
        if ($schedule->campaign === null) {
            return false;
        }

        try {
            app(Z2CampaignSyncService::class)->activate($schedule->campaign);
        } catch (Throwable $exception) {
            $schedule->campaign->update(['status' => 'active']);
            Log::warning('Campaign activated locally but cloud sync failed', [
                'campaign_id' => $schedule->campaign->id,
                'error' => $exception->getMessage(),
            ]);
        }

        return true;
    }

    /** @return Collection<int, Device> */
    private function targetDevices(Schedule $schedule): Collection
    {
        if ($schedule->device !== null) {
            return new Collection([$schedule->device]);
        }

        if ($schedule->group !== null) {
            return $schedule->group->devices
                ->filter(fn (Device $device): bool => filled($device->mac_address))
                ->values();
        }

        // Both target fields empty explicitly means "Todos" in create/edit.
        return Device::query()
            ->whereNotNull('mac_address')
            ->where('mac_address', '!=', '')
            ->get();
    }

    private function finishRun(
        Schedule $schedule,
        bool $success,
        CarbonImmutable $ranAt,
        ?string $error,
    ): void {
        $next = $schedule->isRecurring()
            ? $this->recurrenceService->nextOccurrence($schedule, $ranAt)
            : null;

        $schedule->executed_at = $ranAt;
        $schedule->last_run_status = $success ? 'success' : 'failed';
        $schedule->last_error = $success ? null : $error;

        if ($next !== null) {
            $schedule->scheduled_at = $next;
            $schedule->status = 'pending';
        } else {
            $schedule->status = $success ? 'executed' : 'failed';
        }

        $schedule->save();
    }
}
