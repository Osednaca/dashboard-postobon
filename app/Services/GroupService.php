<?php

namespace App\Services;

use App\Models\Device;
use App\Models\Group;
use App\Repositories\Contracts\DeviceRepositoryInterface;
use App\Repositories\Contracts\GroupRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class GroupService extends BaseService
{
    /**
     * @var DeviceRepositoryInterface
     */
    protected DeviceRepositoryInterface $deviceRepository;

    /**
     * GroupService constructor.
     *
     * @param GroupRepositoryInterface $groupRepository
     * @param DeviceRepositoryInterface $deviceRepository
     */
    public function __construct(GroupRepositoryInterface $groupRepository, DeviceRepositoryInterface $deviceRepository)
    {
        parent::__construct($groupRepository);
        $this->deviceRepository = $deviceRepository;
    }

    /**
     * Power on all devices in a group.
     *
     * @param int|string $groupId
     * @return int
     */
    public function powerOnGroup(int|string $groupId): int
    {
        $devices = $this->deviceRepository->getByGroup($groupId);

        $updated = 0;
        foreach ($devices as $device) {
            $this->deviceRepository->update($device->id, [
                'power_status' => 'on',
                'status' => 'active',
            ]);
            $updated++;
        }

        return $updated;
    }

    /**
     * Power off all devices in a group.
     *
     * @param int|string $groupId
     * @return int
     */
    public function powerOffGroup(int|string $groupId): int
    {
        $devices = $this->deviceRepository->getByGroup($groupId);

        $updated = 0;
        foreach ($devices as $device) {
            $this->deviceRepository->update($device->id, [
                'power_status' => 'off',
                'status' => 'inactive',
            ]);
            $updated++;
        }

        return $updated;
    }

    /**
     * Change content for all devices in a group.
     *
     * @param int|string $groupId
     * @param int|string $campaignId
     * @return int
     */
    public function changeContent(int|string $groupId, int|string $campaignId): int
    {
        $devices = $this->deviceRepository->getByGroup($groupId);

        $updated = 0;
        foreach ($devices as $device) {
            $device->deviceCampaigns()->delete();
            $device->deviceCampaigns()->create([
                'campaign_id' => $campaignId,
            ]);
            $updated++;
        }

        return $updated;
    }

    /**
     * Publish a campaign to all devices in a group.
     *
     * @param int|string $groupId
     * @param int|string $campaignId
     * @return int
     */
    public function publishCampaign(int|string $groupId, int|string $campaignId): int
    {
        return $this->changeContent($groupId, $campaignId);
    }

    /**
     * Get all devices in a group.
     *
     * @param int|string $groupId
     * @return Collection<int, Device>
     */
    public function getDevices(int|string $groupId): Collection
    {
        return $this->deviceRepository->getByGroup($groupId);
    }
}
