<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\Device;
use App\Repositories\Contracts\CampaignRepositoryInterface;
use App\Repositories\Contracts\DeviceRepositoryInterface;

class DashboardService
{
    protected DeviceRepositoryInterface $deviceRepository;

    protected CampaignRepositoryInterface $campaignRepository;

    protected AnalyticsService $analyticsService;

    /**
     * DashboardService constructor.
     */
    public function __construct(
        DeviceRepositoryInterface $deviceRepository,
        CampaignRepositoryInterface $campaignRepository,
        AnalyticsService $analyticsService
    ) {
        $this->deviceRepository = $deviceRepository;
        $this->campaignRepository = $campaignRepository;
        $this->analyticsService = $analyticsService;
    }

    /**
     * Get aggregated KPI data.
     *
     * @return array<string, mixed>
     */
    public function getKpis(): array
    {
        return $this->kpisFromAnalytics($this->analyticsService->report()['kpis']);
    }

    private function kpisFromAnalytics(array $analytics): array
    {
        return [
            'total_devices' => Device::count(),
            'online_devices' => Device::where('status', 'active')->count(),
            'offline_devices' => Device::where('status', 'inactive')->count(),
            'total_working_hours' => $analytics['working_hours'],
            'uptime_percentage' => $analytics['uptime'],
            'average_rpm' => $analytics['average_rpm'],
            'total_impressions' => $analytics['total_impressions'],
            'total_plays' => $analytics['total_plays'],
        ];
    }

    /**
     * Get device statuses breakdown.
     *
     * @return array<string, int>
     */
    public function getDeviceStatuses(): array
    {
        return Device::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
    }

    /**
     * Get campaign statuses breakdown.
     *
     * @return array<string, int>
     */
    public function getCampaignStatuses(): array
    {
        return Campaign::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
    }

    /**
     * Get device map data from establishments, retaining historical coordinates.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getMapData(): array
    {
        return Device::with(['establishmentProfile', 'location'])
            ->get()
            ->map(function (Device $device) {
                $source = collect([$device->establishmentProfile, $device, $device->location])
                    ->first(fn ($candidate): bool => $candidate !== null
                        && is_numeric($candidate->latitude) && is_numeric($candidate->longitude)
                        && is_finite((float) $candidate->latitude) && is_finite((float) $candidate->longitude)
                        && abs((float) $candidate->latitude) <= 90 && abs((float) $candidate->longitude) <= 180);

                if ($source === null) {
                    return null;
                }

                return [
                    'device_id' => $device->id,
                    'device_name' => $device->name,
                    'status' => $device->status,
                    'latitude' => $source->latitude,
                    'longitude' => $source->longitude,
                    'city' => $device->city ?: $device->location?->city,
                    'address' => $source->address,
                ];
            })
            ->filter()
            ->values()
            ->toArray();
    }

    /**
     * Get recent active campaigns.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRecentCampaigns(int $limit = 5): array
    {
        return Campaign::with('creator')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(function (Campaign $campaign) {
                return [
                    'id' => $campaign->id,
                    'name' => $campaign->name,
                    'status' => $campaign->status,
                    'start_date' => $campaign->start_date?->toDateTimeString(),
                    'end_date' => $campaign->end_date?->toDateTimeString(),
                    'creator' => $campaign->creator?->name,
                ];
            })
            ->toArray();
    }

    /**
     * Get dashboard data for the main view.
     *
     * @return array<string, mixed>
     */
    public function getDashboardData(): array
    {
        $report = $this->analyticsService->report();

        return [
            'kpis' => $this->kpisFromAnalytics($report['kpis']),
            'device_statuses' => $this->getDeviceStatuses(),
            'campaign_statuses' => $this->getCampaignStatuses(),
            'map_data' => $this->getMapData(),
            'recent_campaigns' => $this->getRecentCampaigns(),
            'activity_by_city' => $report['cities'],
            'activity_by_group' => $report['groups'],
            'activity_by_campaign' => $report['campaigns'],
        ];
    }
}
