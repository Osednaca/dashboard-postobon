<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\Media;
use App\Models\Schedule;
use App\Models\Wl35DeviceProfile;
use App\Services\Fleet\FleetMediaPlaybackService;
use App\Services\ScheduleRecurrenceService;
use App\Services\Z2\Z2CampaignSyncService;
use App\Services\Z2\Z2DeviceService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ProcessSchedules extends Command
{
    protected $signature = 'schedules:process {--schedule= : Execute only this schedule ID}
        {--force : Execute the selected occurrence now, including a failed attempt}';

    protected $description = 'Execute pending one-time and recurring schedules';

    public function __construct(
        private readonly Z2DeviceService $z2DeviceService,
        private readonly ScheduleRecurrenceService $recurrenceService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        if ($force && $this->option('schedule') === null) {
            $this->error('Para ejecutar inmediatamente, seleccione una programación con --schedule.');

            return self::FAILURE;
        }

        $now = CarbonImmutable::now(config('app.timezone'));
        $pendingSchedules = Schedule::with(['device', 'group.devices', 'group.wl35Devices', 'campaign'])
            ->when($this->option('schedule') !== null, fn ($query) => $query->whereKey($this->option('schedule')))
            ->whereIn('status', $force ? ['pending', 'failed'] : ['pending'])
            ->when(! $force, fn ($query) => $query->where('scheduled_at', '<=', $now))
            ->orderBy('scheduled_at')
            ->get();

        if ($pendingSchedules->isEmpty()) {
            return self::SUCCESS;
        }

        $processed = 0;
        $failed = 0;

        foreach ($pendingSchedules as $schedule) {
            // Playback can take as long as the existing 1900-second fleet job.
            $lock = Cache::lock('schedules:execute:'.$schedule->id, 3600);

            if (! $lock->get()) {
                continue;
            }

            try {
                $plannedAt = $schedule->scheduled_at->copy();
                $schedule->refresh();

                // A simultaneous manual/automatic worker may have finished the
                // occurrence after this command selected its candidates.
                if (! in_array($schedule->status, $force ? ['pending', 'failed'] : ['pending'], true)
                    || ! $schedule->scheduled_at->equalTo($plannedAt)) {
                    continue;
                }

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
            } finally {
                $lock->release();
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
        $wl35Devices = $this->targetWl35Devices($schedule);

        if ($media === null || ($devices->isEmpty() && $wl35Devices->isEmpty())) {
            return false;
        }

        $macs = $devices->pluck('mac_address')
            ->map(fn ($mac): string => strtoupper(str_replace(':', '', trim((string) $mac))))
            ->filter()->unique()->values()->all();
        $wl35Targets = $wl35Devices->pluck('device_id')
            ->map(fn ($id): string => trim((string) $id))
            ->filter()->unique()->map(fn (string $id): string => 'wl35:'.$id)->values()->all();
        $success = ! $devices->contains(fn (Device $device): bool => trim(str_replace(':', '', (string) $device->mac_address)) === '')
            && ! $wl35Devices->contains(fn (Wl35DeviceProfile $device): bool => blank($device->device_id));

        if ($macs !== []) {
            try {
                $result = $this->z2DeviceService->changeVideoOnDevices($macs, $media->file_path);
                foreach ($macs as $mac) {
                    if (($result['results'][$mac] ?? null) !== true) {
                        $success = false;
                    }
                }
            } catch (Throwable $exception) {
                $success = false;
                Log::warning('Scheduled playback failed for 42 devices', [
                    'schedule_id' => $schedule->id, 'error' => $exception->getMessage(),
                ]);
            }
        }

        if ($wl35Targets !== []) {
            $temporaryPath = 'schedule-playback/'.$schedule->id.'-'.Str::uuid().'.mp4';
            try {
                $summary = app(FleetMediaPlaybackService::class)->play(
                    $media, $wl35Targets, $media->original_name ?: basename($media->file_path), $temporaryPath,
                );
                $success = ($summary['success'] ?? false) === true && $success;
            } catch (Throwable $exception) {
                $success = false;
                Log::warning('Scheduled playback failed for 35 devices', [
                    'schedule_id' => $schedule->id, 'error' => $exception->getMessage(),
                ]);
            } finally {
                Storage::disk('local')->delete($temporaryPath);
            }
        }

        return $success && ($macs !== [] || $wl35Targets !== []);
    }

    /** @return Collection<int, Wl35DeviceProfile> */
    private function targetWl35Devices(Schedule $schedule): Collection
    {
        if ($schedule->device_id !== null) {
            return new Collection;
        }

        if ($schedule->group_id !== null) {
            return $schedule->group?->wl35Devices ?? new Collection;
        }

        return Wl35DeviceProfile::all();
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
        if ($schedule->device_id !== null) {
            return new Collection($schedule->device !== null ? [$schedule->device] : []);
        }

        if ($schedule->group_id !== null) {
            $devices = $schedule->group?->devices ?? new Collection;

            return $schedule->type === 'change_content'
                ? $devices
                : $devices->filter(fn (Device $device): bool => filled($device->mac_address))->values();
        }

        // Both target fields empty explicitly means "Todos" in create/edit.
        return Device::query()
            ->when($schedule->type !== 'change_content', fn ($query) => $query
                ->whereNotNull('mac_address')->where('mac_address', '!=', ''))
            ->get();
    }

    private function finishRun(
        Schedule $schedule,
        bool $success,
        CarbonImmutable $ranAt,
        ?string $error,
    ): void {
        $after = $schedule->scheduled_at->greaterThan($ranAt)
            ? CarbonImmutable::instance($schedule->scheduled_at)
            : $ranAt;
        $next = $schedule->isRecurring()
            ? $this->recurrenceService->nextOccurrence($schedule, $after)
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
