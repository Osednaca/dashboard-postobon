<?php

namespace App\Services;

use App\Models\Schedule;
use App\Repositories\Contracts\ScheduleRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;

class ScheduleService extends BaseService
{
    /**
     * ScheduleService constructor.
     */
    public function __construct(
        ScheduleRepositoryInterface $scheduleRepository,
        private readonly ScheduleRecurrenceService $recurrenceService,
    ) {
        parent::__construct($scheduleRepository);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        $data = $this->recurrenceService->prepare($data);
        $data['status'] = $data['status'] ?? 'pending';
        $data['executed_at'] = null;
        $data['last_run_status'] = null;
        $data['last_error'] = null;

        return $this->repository->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(int|string $id, array $data): ?Model
    {
        $schedule = $this->repository->find($id);

        if (! ($schedule instanceof Schedule)) {
            return null;
        }

        $data = $this->recurrenceService->prepare($data, $schedule);
        $data['status'] = 'pending';
        $data['executed_at'] = null;
        $data['last_run_status'] = null;
        $data['last_error'] = null;

        return $this->repository->update($id, $data);
    }

    /**
     * Get pending schedules.
     *
     * @return Collection<int, Schedule>
     */
    public function getPending(): Collection
    {
        return $this->repository->getPending();
    }

    /**
     * Get schedules by device.
     *
     * @return Collection<int, Schedule>
     */
    public function getByDevice(int|string $deviceId): Collection
    {
        return $this->repository->getByDevice($deviceId);
    }

    /**
     * Get schedules by group.
     *
     * @return Collection<int, Schedule>
     */
    public function getByGroup(int|string $groupId): Collection
    {
        return $this->repository->getByGroup($groupId);
    }

    /**
     * Execute a pending schedule by running the schedules:process command for it.
     */
    public function execute(int|string $id): ?Schedule
    {
        $schedule = $this->repository->find($id);

        if (! ($schedule instanceof Schedule)) {
            return null;
        }

        // Ensure it's in pending or failed status before re-executing
        if (! in_array($schedule->status, ['pending', 'failed'])) {
            return $schedule;
        }

        // Force only the selected occurrence; keep its original planned time.
        Artisan::call('schedules:process', ['--schedule' => $schedule->id, '--force' => true]);

        // Refresh and return the updated schedule
        /** @var Schedule|null */
        return $this->repository->find($id);
    }

    /**
     * Cancel a schedule.
     */
    public function cancel(int|string $id): ?Schedule
    {
        $schedule = $this->repository->find($id);

        if ($schedule instanceof Schedule && $schedule->status === 'pending') {
            return $this->repository->update($id, ['status' => 'cancelled']);
        }

        /** @var Schedule|null */
        return $schedule;
    }

    /**
     * Reschedule an existing schedule.
     */
    public function reschedule(int|string $id, string $scheduledAt): ?Schedule
    {
        return $this->repository->update($id, [
            'scheduled_at' => CarbonImmutable::parse($scheduledAt, config('app.timezone'))
                ->timezone(config('app.timezone')),
            'status' => 'pending',
            'executed_at' => null,
            'last_run_status' => null,
            'last_error' => null,
        ]);
    }
}
