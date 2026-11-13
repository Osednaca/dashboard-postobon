<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    public function __construct(protected AnalyticsService $analyticsService) {}

    public function index(): View
    {
        return $this->analyticsView('index');
    }

    public function devices(): View
    {
        return $this->analyticsView('devices');
    }

    public function campaigns(): View
    {
        return $this->analyticsView('campaigns');
    }

    public function groups(): View
    {
        return $this->analyticsView('groups');
    }

    public function cities(): View
    {
        return $this->analyticsView('cities');
    }

    private function analyticsView(string $page): View
    {
        try {
            return view('analytics.'.$page, ['report' => $this->analyticsService->report()]);
        } catch (\Throwable $exception) {
            Log::error('Error al cargar las analíticas.', ['page' => $page, 'exception' => $exception]);

            return view('analytics.'.$page, ['error' => 'No se pudieron cargar las analíticas. Inténtalo de nuevo.']);
        }
    }
}
