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
        return view('dashboard.index', ['data' => $this->dashboard->snapshot()]);
    }
}
