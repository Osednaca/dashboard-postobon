<?php

namespace App\Services;

use App\Models\Schedule;
use App\Repositories\Contracts\ScheduleRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class ScheduleService extends BaseService
{
    /**
     * ScheduleService constructor.
     *
     * @param ScheduleRepositoryInterface $scheduleRepository
     */
    public function __construct(ScheduleRepositoryInterface $scheduleRepository)
    {
        parent::__construct($scheduleRepository);
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
     * @param int|string $deviceId
     * @return Collection<int, Schedule>
     */
    public function getByDevice(int|string $deviceId): Collection
    {
        return $this->repository->getByDevice($deviceId);
    }

    /**
     * Get schedules by group.
     *
     * @param int|string $groupId
     * @return Collection<int, Schedule>
     */
    public function getByGroup(int|string $groupId): Collection
    {
        return $this->repository->getByGroup($groupId);
    }

    /**
     * Execute a pending schedule by running the schedules:process command for it.
     *
     * @param int|string $id
     * @return Schedule|null
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

        // Set scheduled_at to now so the ProcessSchedules command will pick it up
        $this->repository->update($id, [
            'status' => 'pending',
            'scheduled_at' => Carbon::now(),
            'executed_at' => null,
        ]);

        // Run the process command which handles all Z2 API calls
        \Illuminate\Support\Facades\Artisan::call('schedules:process');

        // Refresh and return the updated schedule
        /** @var Schedule|null */
        return $this->repository->find($id);
    }

    /**
     * Cancel a schedule.
     *
     * @param int|string $id
     * @return Schedule|null
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
     *
     * @param int|string $id
     * @param string $scheduledAt
     * @return Schedule|null
     */
    public function reschedule(int|string $id, string $scheduledAt): ?Schedule
    {
        return $this->repository->update($id, [
            'scheduled_at' => $scheduledAt,
            'status' => 'pending',
            'executed_at' => null,
        ]);
    }
}
