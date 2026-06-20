<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;

class AnalyticsController extends Controller
{
    /**
     * @var AnalyticsService
     */
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
            return response()->json([
                'working_hours' => $this->analyticsService->workingHours(),
                'uptime' => $this->analyticsService->uptime(),
                'average_rpm' => $this->analyticsService->averageRpm(),
                'total_impressions' => $this->analyticsService->totalImpressions(),
                'total_plays' => $this->analyticsService->totalPlays(),
                'activity_by_city' => $this->analyticsService->activityByCity(),
                'activity_by_group' => $this->analyticsService->activityByGroup(),
                'activity_by_campaign' => $this->analyticsService->activityByCampaign(),
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
            return response()->json([
                'activity_by_campaign' => $this->analyticsService->activityByCampaign(),
                'total_impressions' => $this->analyticsService->totalImpressions(),
                'total_plays' => $this->analyticsService->totalPlays(),
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
