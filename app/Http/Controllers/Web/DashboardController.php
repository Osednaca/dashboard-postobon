<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use App\Services\Z2\Z2DeviceService;
use App\Services\Z2\Z2GroupService;
use App\Services\Z2\Z2VideoService;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * @var DashboardService
     */
    protected DashboardService $dashboardService;

    /**
     * @var Z2DeviceService
     */
    protected Z2DeviceService $z2DeviceService;

    /**
     * @var Z2GroupService
     */
    protected Z2GroupService $z2GroupService;

    /**
     * @var Z2VideoService
     */
    protected Z2VideoService $z2VideoService;

    /**
     * DashboardController constructor.
     */
    public function __construct(
        DashboardService $dashboardService,
        Z2DeviceService $z2DeviceService,
        Z2GroupService $z2GroupService,
        Z2VideoService $z2VideoService
    ) {
        $this->dashboardService = $dashboardService;
        $this->z2DeviceService = $z2DeviceService;
        $this->z2GroupService = $z2GroupService;
        $this->z2VideoService = $z2VideoService;
    }

    /**
     * Display the dashboard.
     */
    public function index(): View
    {
        try {
            $this->z2DeviceService->syncDevices();
            $this->z2GroupService->syncGroups();
            $this->z2VideoService->syncVideos();

            $data = $this->dashboardService->getDashboardData();

            return view('dashboard.index', compact('data'));
        } catch (\Exception $e) {
            Log::error('Error al cargar el dashboard: ' . $e->getMessage());

            return view('dashboard.index')->with('error', 'Ocurrió un error al cargar el dashboard.');
        }
    }
}
