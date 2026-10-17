<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkRemoveVideoRequest;
use App\Models\Media;
use App\Services\Fleet\DeviceVideoRemovalService;
use Illuminate\Http\JsonResponse;

class DeviceVideoRemovalController extends Controller
{
    public function __invoke(BulkRemoveVideoRequest $request, DeviceVideoRemovalService $removal): JsonResponse
    {
        $data = $request->validated();

        return response()->json(['results' => $removal->remove(
            Media::findOrFail($data['media_id']),
            $data['device_keys'],
            isset($data['group_id']) ? (int) $data['group_id'] : null,
            (int) $request->user()->id,
        )]);
    }
}
