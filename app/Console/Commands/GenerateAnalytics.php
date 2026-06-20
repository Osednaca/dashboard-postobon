<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Models\CampaignStatistic;
use App\Models\Device;
use App\Models\Notification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class GenerateAnalytics extends Command
{
    protected $signature = 'analytics:generate';

    protected $description = 'Generate daily analytics report';

    public function handle(): int
    {
        $this->info('Generating analytics report...');

        $yesterday = Carbon::yesterday();
        $startOfDay = $yesterday->copy()->startOfDay();
        $endOfDay = $yesterday->copy()->endOfDay();

        $this->info("Reporting period: {$startOfDay->toDateTimeString()} - {$endOfDay->toDateTimeString()}");

        // Device analytics
        $totalDevices = Device::count();
        $onlineDevices = Device::where('status', 'online')->count();
        $offlineDevices = Device::where('status', 'offline')->count();
        $maintenanceDevices = Device::where('status', 'maintenance')->count();
        $disabledDevices = Device::where('status', 'disabled')->count();
        $avgWorkingHours = Device::avg('working_hours');

        $this->info('--- Device Analytics ---');
        $this->info("Total devices: {$totalDevices}");
        $this->info("Online: {$onlineDevices}, Offline: {$offlineDevices}, Maintenance: {$maintenanceDevices}, Disabled: {$disabledDevices}");
        $this->info(sprintf("Average working hours: %.2f", $avgWorkingHours));

        // Campaign analytics
        $totalCampaigns = Campaign::count();
        $activeCampaigns = Campaign::where('status', 'active')->count();
        $scheduledCampaigns = Campaign::where('status', 'scheduled')->count();
        $finishedCampaigns = Campaign::where('status', 'finished')->count();
        $pausedCampaigns = Campaign::where('status', 'paused')->count();
        $draftCampaigns = Campaign::where('status', 'draft')->count();

        $this->info('--- Campaign Analytics ---');
        $this->info("Total campaigns: {$totalCampaigns}");
        $this->info("Active: {$activeCampaigns}, Scheduled: {$scheduledCampaigns}, Finished: {$finishedCampaigns}, Paused: {$pausedCampaigns}, Draft: {$draftCampaigns}");

        // Daily statistics
        $dailyStats = CampaignStatistic::whereBetween('date', [$startOfDay->toDateString(), $endOfDay->toDateString()])->get();
        $totalImpressions = $dailyStats->sum('impressions');
        $totalPlays = $dailyStats->sum('plays');
        $totalDuration = $dailyStats->sum('duration');

        $this->info('--- Daily Statistics ---');
        $this->info("Total impressions: {$totalImpressions}");
        $this->info("Total plays: {$totalPlays}");
        $this->info(sprintf("Total duration: %.2f seconds", $totalDuration));

        if ($totalImpressions > 0) {
            $playRate = ($totalPlays / $totalImpressions) * 100;
            $this->info(sprintf("Play rate: %.2f%%", $playRate));
        }

        // Top campaigns by impressions
        $topCampaigns = CampaignStatistic::select('campaign_id')
            ->selectRaw('SUM(impressions) as total_impressions')
            ->whereBetween('date', [$startOfDay->toDateString(), $endOfDay->toDateString()])
            ->groupBy('campaign_id')
            ->orderByDesc('total_impressions')
            ->limit(5)
            ->with('campaign')
            ->get();

        if ($topCampaigns->isNotEmpty()) {
            $this->info('--- Top 5 Campaigns (by impressions) ---');
            foreach ($topCampaigns as $stat) {
                $campaignName = $stat->campaign?->name ?? 'Unknown';
                $this->info("  {$campaignName}: {$stat->total_impressions} impressions");
            }
        }

        // Top devices by plays
        $topDevices = CampaignStatistic::select('device_id')
            ->selectRaw('SUM(plays) as total_plays')
            ->whereBetween('date', [$startOfDay->toDateString(), $endOfDay->toDateString()])
            ->groupBy('device_id')
            ->orderByDesc('total_plays')
            ->limit(5)
            ->with('device')
            ->get();

        if ($topDevices->isNotEmpty()) {
            $this->info('--- Top 5 Devices (by plays) ---');
            foreach ($topDevices as $stat) {
                $deviceName = $stat->device?->name ?? 'Unknown';
                $this->info("  {$deviceName}: {$stat->total_plays} plays");
            }
        }

        // Store report summary
        $report = [
            'generated_at' => now()->toIso8601String(),
            'period' => [
                'start' => $startOfDay->toIso8601String(),
                'end' => $endOfDay->toIso8601String(),
            ],
            'devices' => [
                'total' => $totalDevices,
                'online' => $onlineDevices,
                'offline' => $offlineDevices,
                'maintenance' => $maintenanceDevices,
                'disabled' => $disabledDevices,
                'avg_working_hours' => round($avgWorkingHours, 2),
            ],
            'campaigns' => [
                'total' => $totalCampaigns,
                'active' => $activeCampaigns,
                'scheduled' => $scheduledCampaigns,
                'finished' => $finishedCampaigns,
                'paused' => $pausedCampaigns,
                'draft' => $draftCampaigns,
            ],
            'statistics' => [
                'impressions' => $totalImpressions,
                'plays' => $totalPlays,
                'duration' => round($totalDuration, 2),
            ],
        ];

        // Notify admins
        $adminUsers = \App\Models\User::where('role', 'admin')->get();
        foreach ($adminUsers as $admin) {
            Notification::create([
                'user_id' => $admin->id,
                'type' => 'system',
                'title' => 'Daily Analytics Report',
                'message' => "Daily report for {$yesterday->toDateString()}: {$totalImpressions} impressions, {$totalPlays} plays across {$totalDevices} devices.",
                'data' => $report,
            ]);
        }

        Log::info('Analytics report generated', $report);
        $this->info('Analytics report generated and sent to admins.');

        return self::SUCCESS;
    }
}
