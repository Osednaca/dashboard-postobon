<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;

class AnalyticsController extends Controller
{
    protected AnalyticsService $analyticsService;

    /**
     * AnalyticsController constructor.
     */
    public function __construct(AnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }

    /**
     * Return overall analytics summary.
     */
    public function index(): JsonResponse
    {
        try {
            $report = $this->analyticsService->report();

            return response()->json([
                'working_hours' => $report['kpis']['working_hours'],
                'uptime' => $report['kpis']['uptime'],
                'average_rpm' => $report['kpis']['average_rpm'],
                'total_impressions' => $report['kpis']['total_impressions'],
                'total_plays' => $report['kpis']['total_plays'],
                'activity_by_city' => $report['cities'],
                'activity_by_group' => $report['groups'],
                'activity_by_campaign' => $report['campaigns'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al cargar los analytics.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Return analytics data grouped by devices.
     */
    public function devices(): JsonResponse
    {
        try {
            return response()->json([
                'average_rpm' => $this->analyticsService->averageRpm(),
                'average_heartbeat_rpm' => $this->analyticsService->averageHeartbeatRpm(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al cargar analytics de dispositivos.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Return analytics data grouped by campaigns.
     */
    public function campaigns(): JsonResponse
    {
        try {
            $report = $this->analyticsService->report();

            return response()->json([
                'activity_by_campaign' => $report['campaigns'],
                'total_impressions' => $report['kpis']['total_impressions'],
                'total_plays' => $report['kpis']['total_plays'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al cargar analytics de campañas.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Return analytics data grouped by groups.
     */
    public function groups(): JsonResponse
    {
        try {
            return response()->json([
                'activity_by_group' => $this->analyticsService->activityByGroup(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al cargar analytics de grupos.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Return analytics data grouped by cities.
     */
    public function cities(): JsonResponse
    {
        try {
            return response()->json([
                'activity_by_city' => $this->analyticsService->activityByCity(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al cargar analytics de ciudades.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
