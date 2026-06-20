<?php

namespace App\Repositories\Contracts;

use App\Models\Schedule;
use Illuminate\Database\Eloquent\Collection;

interface ScheduleRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Get pending schedules.
     *
     * @return Collection<int, Schedule>
     */
    public function getPending(): Collection;

    /**
     * Get schedules by device ID.
     *
     * @param int|string $deviceId
     * @return Collection<int, Schedule>
     */
    public function getByDevice(int|string $deviceId): Collection;

    /**
     * Get schedules by group ID.
     *
     * @param int|string $groupId
     * @return Collection<int, Schedule>
     */
    public function getByGroup(int|string $groupId): Collection;
}
