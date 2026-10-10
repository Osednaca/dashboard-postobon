<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Services\Fleet\DevicePreviewService;
use Illuminate\Http\JsonResponse;

class DevicePreviewController extends Controller
{
    public function __invoke(DevicePreviewService $previews): JsonResponse
    {
        $this->authorize('viewAny', Device::class);

        return response()->json(['devices' => $previews->snapshot()])->header('Cache-Control', 'private, no-store');
    }
}
