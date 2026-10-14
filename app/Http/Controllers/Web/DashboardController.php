<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\DashboardSnapshotService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardSnapshotService $dashboard) {}

    public function index(): View
    {
        $data = $this->dashboard->snapshot();
        if (! auth()->user()->viewsDevices()) {
            foreach ($data['map_data'] as &$establishment) {
                foreach ($establishment['devices'] as &$device) {
                    $device['detail_url'] = null;
                }
            }
            unset($establishment, $device);
        }

        return view('dashboard.index', compact('data'));
    }
}
