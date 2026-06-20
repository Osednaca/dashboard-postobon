<?php

namespace App\Repositories;

use App\Models\Schedule;
use App\Repositories\Contracts\ScheduleRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class ScheduleRepository extends BaseRepository implements ScheduleRepositoryInterface
{
    /**
     * ScheduleRepository constructor.
     *
     * @param Schedule $schedule
     */
    public function __construct(Schedule $schedule)
    {
        parent::__construct($schedule);
    }

    /**
     * @inheritDoc
     */
    public function getPending(): Collection
    {
        return $this->model
            ->where('status', 'pending')
            ->where('scheduled_at', '<=', Carbon::now())
            ->get();
    }

    /**
     * @inheritDoc
     */
    public function getByDevice(int|string $deviceId): Collection
    {
        return $this->model->where('device_id', $deviceId)->get();
    }

    /**
     * @inheritDoc
     */
    public function getByGroup(int|string $groupId): Collection
    {
        return $this->model->where('group_id', $groupId)->get();
    }
}
