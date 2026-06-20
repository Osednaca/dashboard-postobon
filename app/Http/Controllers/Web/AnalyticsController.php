<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

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
     * Display the main analytics dashboard.
     */
    public function index(): View
    {
        try {
            $kpis = [
                'working_hours' => $this->analyticsService->workingHours(),
                'uptime' => $this->analyticsService->uptime(),
                'average_rpm' => $this->analyticsService->averageRpm(),
                'total_impressions' => $this->analyticsService->totalImpressions(),
                'total_plays' => $this->analyticsService->totalPlays(),
            ];

            return view('analytics.index', compact('kpis'));
        } catch (\Exception $e) {
            Log::error('Error al cargar analytics: ' . $e->getMessage());

            return view('analytics.index')->with('error', 'Ocurrió un error al cargar los analytics.');
        }
    }

    /**
     * Display device analytics.
     */
    public function devices(): View
    {
        try {
            $averageRpm = $this->analyticsService->averageRpm();
            $uptime = $this->analyticsService->uptime();
            $averageHeartbeatRpm = $this->analyticsService->averageHeartbeatRpm();

            return view('analytics.devices', compact('averageRpm', 'uptime', 'averageHeartbeatRpm'));
        } catch (\Exception $e) {
            Log::error('Error al cargar analytics de dispositivos: ' . $e->getMessage());

            return view('analytics.devices')->with('error', 'Ocurrió un error al cargar los analytics de dispositivos.');
        }
    }

    /**
     * Display campaign analytics.
     */
    public function campaigns(): View
    {
        try {
            $activityByCampaign = $this->analyticsService->activityByCampaign();
            $totalImpressions = $this->analyticsService->totalImpressions();
            $totalPlays = $this->analyticsService->totalPlays();

            return view('analytics.campaigns', compact('activityByCampaign', 'totalImpressions', 'totalPlays'));
        } catch (\Exception $e) {
            Log::error('Error al cargar analytics de campañas: ' . $e->getMessage());

            return view('analytics.campaigns')->with('error', 'Ocurrió un error al cargar los analytics de campañas.');
        }
    }

    /**
     * Display group analytics.
     */
    public function groups(): View
    {
        try {
            $activityByGroup = $this->analyticsService->activityByGroup();

            return view('analytics.groups', compact('activityByGroup'));
        } catch (\Exception $e) {
            Log::error('Error al cargar analytics de grupos: ' . $e->getMessage());

            return view('analytics.groups')->with('error', 'Ocurrió un error al cargar los analytics de grupos.');
        }
    }

    /**
     * Display city analytics.
     */
    public function cities(): View
    {
        try {
            $activityByCity = $this->analyticsService->activityByCity();

            return view('analytics.cities', compact('activityByCity'));
        } catch (\Exception $e) {
            Log::error('Error al cargar analytics de ciudades: ' . $e->getMessage());

            return view('analytics.cities')->with('error', 'Ocurrió un error al cargar los analytics de ciudades.');
        }
    }
}
