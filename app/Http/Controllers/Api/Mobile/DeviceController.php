<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\MobileListRequest;
use App\Models\Device;
use App\Models\Establishment;
use App\Services\Mobile\MobileDeviceList;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

class DeviceController extends Controller
{
    public function __construct(private readonly MobileDeviceList $devices) {}

    public function index(MobileListRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Device::class);

        return $this->response($this->devices->paginate($request->validated(), $request->url()));
    }

    public function establishment(MobileListRequest $request, Establishment $establishment): JsonResponse
    {
        $this->authorize('view', $establishment);

        return $this->response($this->devices->paginate([
            ...$request->validated(), 'establishment_id' => $establishment->id,
        ], $request->url()));
    }

    private function response(LengthAwarePaginator $page): JsonResponse
    {
        return response()->json([
            'data' => $page->items(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
            'links' => ['first' => $page->url(1), 'last' => $page->url($page->lastPage()), 'prev' => $page->previousPageUrl(), 'next' => $page->nextPageUrl()],
        ]);
    }
}
