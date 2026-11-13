<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\CampaignStatistic;
use App\Models\Device;
use App\Models\DeviceHeartbeat;
use App\Models\Group;
use App\Models\Wl35DeviceProfile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AnalyticsService
{
    /** Local, persisted data only. Reading analytics never synchronizes or commands hardware. */
    public function report(): array
    {
        $plays = CampaignStatistic::whereHas('campaign')->selectRaw('device_id, SUM(plays) as total_plays')
            ->groupBy('device_id')->pluck('total_plays', 'device_id');
        $devices = [];
        foreach (Device::with(['group', 'location', 'establishmentProfile'])->orderBy('id')->get() as $device) {
            $mac = strtoupper(str_replace([':', '-'], '', trim($device->mac_address ?? '')));
            $key = 'z2:'.($mac ?: 'local-'.$device->id);
            if (isset($devices[$key])) {
                $devices[$key]['total_plays'] += (int) ($plays[$device->id] ?? 0);

                continue;
            }
            $devices[$key] = $this->deviceRow($device, $key, '42') + [
                'working_hours' => (float) $device->working_hours,
                'rpm' => $device->rpm === null ? null : (float) $device->rpm,
                'status' => match ($device->status) {
                    'online', 'active' => 'online',
                    'offline', 'inactive' => 'offline',
                    default => 'unknown',
                },
                'total_plays' => (int) ($plays[$device->id] ?? 0),
            ];
        }
        foreach (Wl35DeviceProfile::with(['group', 'location', 'establishmentProfile'])->orderBy('id')->get() as $profile) {
            $key = 'wl35:'.trim($profile->device_id);
            $devices[$key] ??= $this->deviceRow($profile, $key, '35') + [
                'working_hours' => null, 'rpm' => null, 'status' => 'unknown', 'total_plays' => null,
            ];
        }
        $devices = collect($devices)->sortBy('name')->values();
        $groups = Group::orderBy('name')->orderBy('id')->get()->map(fn (Group $group): array => ['id' => $group->id, 'group' => $group->name, 'name' => $group->name]
            + $this->inventoryTotals($devices->where('group_id', $group->id)))->all();
        $ungrouped = $devices->whereNull('group_id');
        if ($ungrouped->isNotEmpty()) {
            $groups[] = ['id' => null, 'group' => 'Sin grupo', 'name' => 'Sin grupo'] + $this->inventoryTotals($ungrouped);
        }
        $cities = $devices->groupBy('city')->sortKeys()->map(fn ($rows, $city): array => ['city' => $city] + $this->inventoryTotals($rows))->values()->all();
        $campaigns = Campaign::withCount('statistics')->withSum('statistics', 'plays')
            ->withSum('statistics', 'impressions')->orderBy('name')->orderBy('id')->get()
            ->map(fn (Campaign $campaign): array => [
                'id' => $campaign->id, 'campaign' => $campaign->name, 'name' => $campaign->name,
                'status' => $campaign->status, 'total_plays' => (int) $campaign->statistics_sum_plays,
                'total_impressions' => (int) $campaign->statistics_sum_impressions,
                'statistics_count' => $campaign->statistics_count,
            ])->all();
        $totals = $this->inventoryTotals($devices);
        $rpm = $devices->pluck('rpm')->filter(fn ($value): bool => $value !== null);

        return [
            'kpis' => $totals + [
                'uptime' => $devices->isEmpty() ? 0.0 : round($totals['online_count'] / $devices->count() * 100, 2),
                'average_rpm' => (float) ($rpm->avg() ?? 0),
                'total_campaigns' => count($campaigns),
                'total_impressions' => array_sum(array_column($campaigns, 'total_impressions')),
                'total_plays' => array_sum(array_column($campaigns, 'total_plays')),
            ],
            'devices' => $devices->all(), 'groups' => $groups, 'cities' => $cities, 'campaigns' => $campaigns,
        ];
    }

    private function deviceRow(Device|Wl35DeviceProfile $device, string $key, string $type): array
    {
        return [
            'key' => $key, 'type' => $type, 'name' => $device->name ?: $key,
            'group_id' => $device->group?->id, 'group' => $device->group?->name ?? 'Sin grupo',
            'city' => trim($device->city ?? '') ?: (trim($device->location?->city ?? '') ?: 'Sin ciudad'),
            'establishment' => $device->establishmentProfile?->name ?: ($device->establishment ?: 'Sin establecimiento'),
        ];
    }

    private function inventoryTotals(Collection $devices): array
    {
        return [
            'device_count' => $devices->count(),
            'online_count' => $devices->where('status', 'online')->count(),
            'offline_count' => $devices->where('status', 'offline')->count(),
            'unknown_count' => $devices->where('status', 'unknown')->count(),
            'working_hours' => (float) $devices->sum('working_hours'),
            'hours_device_count' => $devices->whereNotNull('working_hours')->count(),
        ];
    }

    public function workingHours(): float
    {
        return $this->report()['kpis']['working_hours'];
    }

    /** Legacy API key: recorded online share, not historical uptime. */
    public function uptime(): float
    {
        return $this->report()['kpis']['uptime'];
    }

    public function averageRpm(): float
    {
        return $this->report()['kpis']['average_rpm'];
    }

    public function activityByCity(): array
    {
        return $this->report()['cities'];
    }

    public function activityByGroup(): array
    {
        return $this->report()['groups'];
    }

    public function activityByCampaign(): array
    {
        return $this->report()['campaigns'];
    }

    public function averageHeartbeatRpm(int $hours = 24): float
    {
        return (float) DeviceHeartbeat::where('received_at', '>=', Carbon::now()->subHours($hours))->avg('rpm');
    }

    public function totalImpressions(): int
    {
        return (int) $this->report()['kpis']['total_impressions'];
    }

    public function totalPlays(): int
    {
        return (int) $this->report()['kpis']['total_plays'];
    }
}
