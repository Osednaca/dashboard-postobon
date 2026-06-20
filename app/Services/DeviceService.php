<?php

namespace App\Services;

use App\Models\Device;
use App\Repositories\Contracts\DeviceRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;

class DeviceService extends BaseService
{
    /**
     * DeviceService constructor.
     *
     * @param DeviceRepositoryInterface $deviceRepository
     */
    public function __construct(DeviceRepositoryInterface $deviceRepository)
    {
        parent::__construct($deviceRepository);
    }

    /**
     * Find a device by its MAC address.
     *
     * @param string $macAddress
     * @return Device|null
     */
    public function findByMac(string $macAddress): ?Device
    {
        /** @var Device|null */
        return $this->repository->findByMac($macAddress);
    }

    /**
     * Get devices by group.
     *
     * @param int|string $groupId
     * @return Collection<int, Device>
     */
    public function getByGroup(int|string $groupId): Collection
    {
        return $this->repository->getByGroup($groupId);
    }

    /**
     * Get devices by location.
     *
     * @param int|string $locationId
     * @return Collection<int, Device>
     */
    public function getByLocation(int|string $locationId): Collection
    {
        return $this->repository->getByLocation($locationId);
    }

    /**
     * Power on a device.
     *
     * @param int|string $id
     * @return Device|null
     */
    public function powerOn(int|string $id): ?Device
    {
        return $this->repository->update($id, [
            'power_status' => 'on',
            'status' => 'active',
        ]);
    }

    /**
     * Power off a device.
     *
     * @param int|string $id
     * @return Device|null
     */
    public function powerOff(int|string $id): ?Device
    {
        return $this->repository->update($id, [
            'power_status' => 'off',
            'status' => 'inactive',
        ]);
    }

    /**
     * Disable a device.
     *
     * @param int|string $id
     * @return Device|null
     */
    public function disable(int|string $id): ?Device
    {
        return $this->repository->update($id, [
            'status' => 'disabled',
        ]);
    }

    /**
     * Enable a device.
     *
     * @param int|string $id
     * @return Device|null
     */
    public function enable(int|string $id): ?Device
    {
        return $this->repository->update($id, [
            'status' => 'active',
        ]);
    }

    /**
     * Change a device's group.
     *
     * @param int|string $id
     * @param int|string $groupId
     * @return Device|null
     */
    public function changeGroup(int|string $id, int|string $groupId): ?Device
    {
        return $this->repository->update($id, [
            'group_id' => $groupId,
        ]);
    }

    /**
     * Change a device's location.
     *
     * @param int|string $id
     * @param int|string $locationId
     * @return Device|null
     */
    public function changeLocation(int|string $id, int|string $locationId): ?Device
    {
        return $this->repository->update($id, [
            'location_id' => $locationId,
        ]);
    }

    /**
     * Assign content to a device.
     *
     * @param int|string $id
     * @param int|string $campaignId
     * @return Device|null
     */
    public function assignContent(int|string $id, int|string $campaignId): ?Device
    {
        $device = $this->repository->find($id);

        if ($device instanceof Device) {
            $device->deviceCampaigns()->create([
                'campaign_id' => $campaignId,
            ]);
        }

        return $device;
    }

    /**
     * Unbind content from a device.
     *
     * @param int|string $id
     * @param int|string|null $campaignId
     * @return Device|null
     */
    public function unbind(int|string $id, int|string|null $campaignId = null): ?Device
    {
        $device = $this->repository->find($id);

        if ($device instanceof Device) {
            $query = $device->deviceCampaigns();

            if ($campaignId !== null) {
                $query->where('campaign_id', $campaignId);
            }

            $query->delete();
        }

        return $device;
    }

    /**
     * Sync device with an external API.
     *
     * @param int|string $id
     * @return array<string, mixed>|null
     */
    public function syncWithExternalApi(int|string $id): ?array
    {
        $device = $this->repository->find($id);

        if (! $device instanceof Device) {
            return null;
        }

        try {
            $response = Http::timeout(10)
                ->withToken(config('services.external_api.token'))
                ->get(config('services.external_api.base_url') . '/devices/' . $device->mac_address);

            if ($response->successful()) {
                $data = $response->json();

                $this->repository->update($id, [
                    'firmware' => $data['firmware'] ?? $device->firmware,
                    'hardware' => $data['hardware'] ?? $device->hardware,
                    'status' => $data['status'] ?? $device->status,
                ]);

                return $data;
            }
        } catch (\Exception $e) {
            report($e);
        }

        return null;
    }
}
