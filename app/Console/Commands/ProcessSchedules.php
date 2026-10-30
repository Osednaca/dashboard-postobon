<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\Media;
use App\Models\Schedule;
use App\Models\Wl35DeviceMedia;
use App\Models\Wl35DeviceProfile;
use App\Services\Fleet\DeviceMutationLock;
use App\Services\Fleet\FleetMediaPlaybackService;
use App\Services\Fleet\UnifiedFleetClient;
use App\Services\ScheduleRecurrenceService;
use App\Services\Z2\Z2CampaignSyncService;
use App\Services\Z2\Z2DeviceService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ProcessSchedules extends Command
{
    protected $signature = 'schedules:process {--schedule= : Execute only this schedule ID}
        {--force : Execute the selected occurrence now, including a failed attempt}';

    protected $description = 'Execute pending one-time and recurring schedules';

    private ?string $controlError = null;

    private array $powerDiagnosticContext = [];

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
            $this->powerDiagnosticContext = [];
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

                $this->controlError = null;
                if (in_array($schedule->type, ['power_on', 'power_off'], true)) {
                    $this->powerDiagnosticContext = [
                        'revision' => 'power-scope-v1', 'run_id' => (string) Str::uuid(),
                        'mode' => $force ? 'manual' : 'automatic', 'schedule_id' => $schedule->id,
                        'type' => $schedule->type, 'stored_scope' => $schedule->target_scope,
                        'resolved_scope' => $schedule->controlTargetScope(),
                        'group_id' => $schedule->group_id, 'device_id' => $schedule->device_id,
                    ];
                    $this->powerDiagnostic('started');
                }
                $success = match ($schedule->type) {
                    'power_on', 'power_off', 'format_sd' => $this->executeControl($schedule),
                    'change_content' => $this->executeChangeContent($schedule),
                    'activate_campaign' => $this->executeActivateCampaign($schedule),
                    default => false,
                };

                if (! $success && $schedule->type === 'activate_campaign' && $schedule->campaign?->fresh()?->duration_preparation_state === 'pending') {
                    // Do not claim activation or fail the occurrence while its metadata job runs.
                    continue;
                }

                $this->finishRun($schedule, $success, $now, $success ? null : ($this->controlError ?? 'El dispositivo o la nube rechazó la instrucción.'));
                $this->powerDiagnostic('completed', ['accepted' => $success]);
                if ($success) {
                    $processed++;
                } else {
                    $failed++;
                }
            } catch (Throwable $exception) {
                $this->powerDiagnostic('failed', ['error_class' => $exception::class]);
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

    private function executeControl(Schedule $schedule): bool
    {
        $scope = $schedule->controlTargetScope();
        if ($scope === null) {
            $this->powerDiagnostic('rejected', ['reason' => 'ambiguous_scope', 'requested_targets' => []]);
            $this->controlError = 'Destino sin confirmar. Edita la programación y selecciona el grupo, dispositivo o Todos.';
            Log::warning('Scheduled control rejected ambiguous scope', ['schedule_id' => $schedule->id,
                'group_id' => $schedule->group_id, 'device_id' => $schedule->device_id]);

            return false;
        }

        // Snapshot only persisted membership; a missing target never becomes all.
        $devices = Device::query()
            ->whereNotIn('id', $this->excludedDeviceIds($schedule))
            ->when($scope === 'device', fn ($query) => $query->whereKey($schedule->device_targets !== null
                ? $schedule->selectedDeviceIds('z2') : [$schedule->device_id]))
            ->when($scope === 'group', fn ($query) => $query->where('group_id', $schedule->group_id))
            ->get();
        $profiles = Wl35DeviceProfile::query()
            ->when($scope === 'device', fn ($query) => $query->whereKey($schedule->selectedDeviceIds('wl35')))
            ->when($scope === 'group', fn ($query) => $query->where('group_id', $schedule->group_id))
            ->get();
        if ($scope === 'group' && $schedule->group === null) {
            $devices = collect();
            $profiles = collect();
        }

        $targets = [];
        $errors = [];
        if ($scope === 'device' && $schedule->device_targets !== null
            && $devices->count() + $profiles->count() !== count($schedule->device_targets)) {
            $errors[] = 'Uno o más dispositivos seleccionados ya no están disponibles';
        }
        foreach ($devices as $device) {
            $mac = strtoupper(str_replace(':', '', trim((string) $device->mac_address)));
            if (! preg_match('/^[A-Z0-9]+$/D', $mac)) {
                $errors[] = 'Equipo 42 #'.$device->id.': identificación inválida';

                continue;
            }
            $targets['z2:'.$mac] = ['type' => 'z2', 'id' => $device->mac_address];
        }
        foreach ($profiles as $profile) {
            $id = (string) $profile->device_id;
            if (! preg_match('/^[A-Za-z0-9_.:-]+$/D', $id)) {
                $errors[] = 'Equipo 35 #'.$profile->id.': identificación inválida';

                continue;
            }
            $targets['wl35:'.$id] = ['type' => 'wl35', 'id' => $id];
        }
        Log::info('Scheduled control resolved recipients', ['schedule_id' => $schedule->id,
            'type' => $schedule->type, 'scope' => $scope, 'group_id' => $schedule->group_id,
            'requested_targets' => array_keys($targets)]);
        $this->powerDiagnostic('resolved', ['requested_targets' => array_keys($targets),
            'target_count' => count($targets), 'invalid_target_count' => count($errors)]);

        foreach ($targets as $key => $target) {
            try {
                if ($target['type'] === 'z2') {
                    $accepted = match ($schedule->type) {
                        'power_on' => $this->z2DeviceService->powerOn($target['id']),
                        'power_off' => $this->z2DeviceService->powerOff($target['id']),
                        'format_sd' => $this->z2DeviceService->formatSd($target['id']),
                    };
                } else {
                    $accepted = app(DeviceMutationLock::class)->run([$key], function () use ($schedule, $key, $target): bool {
                        $payload = ['command' => $schedule->type === 'format_sd' ? 'format_sd' : 'power',
                            'targets' => [$key]];
                        if ($payload['command'] === 'power') {
                            $payload['value'] = $schedule->type === 'power_on';
                        }
                        $response = app(UnifiedFleetClient::class)->sendCommand($payload, $schedule->type === 'format_sd');
                        $results = $response['results'] ?? null;
                        $result = is_array($results) && array_is_list($results) && count($results) === 1 ? $results[0] : null;
                        $accepted = is_array($result)
                            && (! array_key_exists('success', $response) || $response['success'] === true)
                            && (! array_key_exists('command', $response) || $response['command'] === $payload['command'])
                            && ($result['key'] ?? null) === $key
                            && ($result['type'] ?? null) === 'wl35'
                            && ($result['id'] ?? null) === $target['id']
                            && ($result['success'] ?? null) === true;
                        if ($accepted && $schedule->type === 'format_sd') {
                            Wl35DeviceMedia::where('device_id', $target['id'])->delete();
                        }

                        return $accepted;
                    });
                }
                Log::info('Scheduled control recipient result', ['schedule_id' => $schedule->id,
                    'type' => $schedule->type, 'group_id' => $schedule->group_id,
                    'target' => $key, 'accepted' => $accepted]);
                $this->powerDiagnostic('recipient', ['target' => $key, 'accepted' => $accepted]);
                if (! $accepted) {
                    $errors[] = $key.': la orden no fue confirmada para este destinatario';
                }
            } catch (Throwable $exception) {
                $this->powerDiagnostic('recipient', ['target' => $key, 'accepted' => false,
                    'error_class' => $exception::class]);
                $errors[] = $key.': no se pudo completar o confirmar la solicitud';
                Log::warning('Scheduled control recipient exception', ['schedule_id' => $schedule->id,
                    'type' => $schedule->type, 'group_id' => $schedule->group_id,
                    'target' => $key, 'error' => $exception->getMessage()]);
            }
        }
        if ($targets === []) {
            $errors[] = 'No hay dispositivos válidos en el destino seleccionado';
        }
        if ($errors !== []) {
            $this->powerDiagnostic('rejected', ['reason' => $targets === [] ? 'no_valid_targets' : 'recipient_failure',
                'error_count' => count($errors)]);
            $this->controlError = implode('; ', $errors);
            Log::warning('Scheduled control has failed recipients', ['schedule_id' => $schedule->id,
                'type' => $schedule->type, 'group_id' => $schedule->group_id,
                'requested_targets' => array_keys($targets), 'errors' => $errors]);
        }

        return $errors === [];
    }

    private function powerDiagnostic(string $event, array $details = []): void
    {
        if ($this->powerDiagnosticContext === []) {
            return;
        }

        try {
            Log::channel('schedule_power')->info('SCHEDULE_POWER_DIAGNOSTIC',
                array_merge($this->powerDiagnosticContext, ['event' => $event], $details));
        } catch (Throwable) {
            // Diagnostic availability must never change command delivery or retries.
        }
    }

    private function executeChangeContent(Schedule $schedule): bool
    {
        $scope = $schedule->controlTargetScope();
        $legacyAll = $schedule->target_scope === null && $schedule->device_id === null
            && $schedule->group_id === null && $schedule->device_targets === null;
        if ($scope === null && ! $legacyAll) {
            return false;
        }
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
        if ($scope === 'device' && $schedule->device_targets !== null
            && $devices->count() + $wl35Devices->count() !== count($schedule->device_targets)) {
            $success = false;
        }

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
        if ($schedule->device_targets !== null) {
            return Wl35DeviceProfile::whereKey($schedule->selectedDeviceIds('wl35'))->get();
        }
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

        if ($schedule->campaign->duration_preparation_state === 'failed') {
            $this->controlError = $schedule->campaign->lifecycle_error ?: 'No se pudo preparar la duración de los videos.';

            return false;
        }

        return app(Z2CampaignSyncService::class)->activate($schedule->campaign);
    }

    /** @return Collection<int, Device> */
    private function targetDevices(Schedule $schedule): Collection
    {
        if ($schedule->device_targets !== null) {
            return Device::whereKey($schedule->selectedDeviceIds('z2'))
                ->whereNotIn('id', $this->excludedDeviceIds($schedule))->get();
        }
        if ($schedule->device_id !== null) {
            return Device::whereKey($schedule->device_id)->whereNotIn('id', $this->excludedDeviceIds($schedule))->get();
        }

        if ($schedule->group_id !== null) {
            $devices = $schedule->group === null ? new Collection : Device::where('group_id', $schedule->group_id)
                ->whereNotIn('id', $this->excludedDeviceIds($schedule))->get();

            return $schedule->type === 'change_content'
                ? $devices
                : $devices->filter(fn (Device $device): bool => filled($device->mac_address))->values();
        }

        // Both target fields empty explicitly means "Todos" in create/edit.
        return Device::query()
            ->whereNotIn('id', $this->excludedDeviceIds($schedule))
            ->when($schedule->type !== 'change_content', fn ($query) => $query
                ->whereNotNull('mac_address')->where('mac_address', '!=', ''))
            ->get();
    }

    private function excludedDeviceIds(Schedule $schedule): QueryBuilder
    {
        return DB::table('device_retirement_exclusions')->select('device_id')
            ->where('scope', 'schedule')->where('resource_id', (string) $schedule->id);
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
