<?php

namespace App\Services;

use App\Models\CampaignStatistic;
use App\Models\Device;
use App\Models\DeviceHeartbeat;
use App\Repositories\Contracts\CampaignStatisticRepositoryInterface;
use App\Repositories\Contracts\DeviceHeartbeatRepositoryInterface;
use App\Repositories\Contracts\DeviceRepositoryInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    /**
     * @var DeviceRepositoryInterface
     */
    protected DeviceRepositoryInterface $deviceRepository;

    /**
     * @var DeviceHeartbeatRepositoryInterface
     */
    protected DeviceHeartbeatRepositoryInterface $heartbeatRepository;

    /**
     * @var CampaignStatisticRepositoryInterface
     */
    protected CampaignStatisticRepositoryInterface $statisticRepository;

    /**
     * AnalyticsService constructor.
     *
     * @param DeviceRepositoryInterface $deviceRepository
     * @param DeviceHeartbeatRepositoryInterface $heartbeatRepository
     * @param CampaignStatisticRepositoryInterface $statisticRepository
     */
    public function __construct(
        DeviceRepositoryInterface $deviceRepository,
        DeviceHeartbeatRepositoryInterface $heartbeatRepository,
        CampaignStatisticRepositoryInterface $statisticRepository
    ) {
        $this->deviceRepository = $deviceRepository;
        $this->heartbeatRepository = $heartbeatRepository;
        $this->statisticRepository = $statisticRepository;
    }

    /**
     * Calculate total working hours across all devices.
     *
     * @return float
     */
    public function workingHours(): float
    {
        return (float) Device::sum('working_hours');
    }

    /**
     * Calculate device uptime percentage.
     *
     * @return float
     */
    public function uptime(): float
    {
        $total = Device::count();
        $online = Device::where('status', 'active')->count();

        if ($total === 0) {
            return 0.0;
        }

        return round(($online / $total) * 100, 2);
    }

    /**
     * Calculate average RPM across all devices.
     *
     * @return float
     */
    public function averageRpm(): float
    {
        return (float) Device::avg('rpm') ?? 0.0;
    }

    /**
     * Get activity grouped by city.
     *
     * @return array<int, array<string, mixed>>
     */
    public function activityByCity(): array
    {
        return Device::select('locations.city', DB::raw('COUNT(devices.id) as device_count'))
            ->join('locations', 'devices.location_id', '=', 'locations.id')
            ->groupBy('locations.city')
            ->get()
            ->map(fn ($item) => [
                'city' => $item->city,
                'device_count' => (int) $item->device_count,
            ])
            ->toArray();
    }

    /**
     * Get activity grouped by group.
     *
     * @return array<int, array<string, mixed>>
     */
    public function activityByGroup(): array
    {
        return Device::select('groups.name', DB::raw('COUNT(devices.id) as device_count'))
            ->join('groups', 'devices.group_id', '=', 'groups.id')
            ->groupBy('groups.name')
            ->get()
            ->map(fn ($item) => [
                'group' => $item->name,
                'device_count' => (int) $item->device_count,
            ])
            ->toArray();
    }

    /**
     * Get activity grouped by campaign.
     *
     * @return array<int, array<string, mixed>>
     */
    public function activityByCampaign(): array
    {
        return CampaignStatistic::select('campaigns.name', DB::raw('SUM(campaign_statistics.plays) as total_plays'))
            ->join('campaigns', 'campaign_statistics.campaign_id', '=', 'campaigns.id')
            ->groupBy('campaigns.name')
            ->get()
            ->map(fn ($item) => [
                'campaign' => $item->name,
                'total_plays' => (int) $item->total_plays,
            ])
            ->toArray();
    }

    /**
     * Get average heartbeat RPM over the last N hours.
     *
     * @param int $hours
     * @return float
     */
    public function averageHeartbeatRpm(int $hours = 24): float
    {
        return (float) DeviceHeartbeat::where('received_at', '>=', Carbon::now()->subHours($hours))
            ->avg('rpm') ?? 0.0;
    }

    /**
     * Get total impressions.
     *
     * @return int
     */
    public function totalImpressions(): int
    {
        return (int) CampaignStatistic::sum('impressions');
    }

    /**
     * Get total plays.
     *
     * @return int
     */
    public function totalPlays(): int
    {
        return (int) CampaignStatistic::sum('plays');
    }
}
