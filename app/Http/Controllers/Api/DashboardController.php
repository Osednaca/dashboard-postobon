<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class DashboardController extends Controller
{
    /**
     * @var DashboardService
     */
    protected DashboardService $dashboardService;

    /**
     * DashboardController constructor.
     */
    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    /**
     * Return dashboard data with KPIs, statistics, and map data.
     */
    public function index(): JsonResponse|RedirectResponse
    {
        if ($redirect = $this->redirectBrowserToWeb('dashboard')) {
            return $redirect;
        }

        try {
            $data = $this->dashboardService->getDashboardData();

            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al cargar el dashboard.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
