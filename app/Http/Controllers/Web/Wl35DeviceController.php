<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteWl35VideoRequest;
use App\Http\Requests\SetWl35VolumeRequest;
use App\Http\Requests\UpdateWl35DeviceProfileRequest;
use App\Models\Device;
use App\Models\Group;
use App\Models\Location;
use App\Models\Wl35DeviceProfile;
use App\Services\Fleet\UnifiedFleetClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class Wl35DeviceController extends Controller
{
    public function __construct(private readonly UnifiedFleetClient $fleetClient) {}

    public function show(string $deviceId): View
    {
        $this->authorize('viewAny', Device::class);

        $profile = Wl35DeviceProfile::with(['location', 'group'])
            ->where('device_id', $deviceId)
            ->first();
        $gatewayError = null;
        $device = null;

        try {
            $device = collect($this->fleetClient->getFleet()['devices'] ?? [])
                ->first(fn (array $item): bool => ($item['type'] ?? null) === 'wl35'
                    && (string) ($item['id'] ?? '') === $deviceId);
        } catch (Throwable $exception) {
            $gatewayError = $exception->getMessage();
            Log::warning('No fue posible consultar el estado del detalle WL35.', [
                'device_id' => $deviceId,
                'error' => $exception->getMessage(),
            ]);
        }

        abort_if($device === null && $profile === null, 404);

        $device ??= [
            'id' => $deviceId,
            'key' => 'wl35:'.$deviceId,
            'name' => $profile?->name ?? $deviceId,
            'online' => false,
            'connected' => false,
            'power' => false,
            'bluetooth' => false,
            'volume' => null,
            'session_ready' => false,
            'current_video' => null,
            'video_count' => 0,
            'ip' => null,
            'firmware_version' => null,
            'last_seen' => null,
            'uploading' => false,
        ];

        $locations = Location::orderBy('name')->get();
        $groups = Group::orderBy('name')->get();

        return view('devices.wl35-show', compact(
            'device',
            'profile',
            'locations',
            'groups',
            'gatewayError'
        ));
    }

    public function update(
        UpdateWl35DeviceProfileRequest $request,
        string $deviceId,
    ): RedirectResponse {
        $this->authorize('create', Device::class);

        Wl35DeviceProfile::updateOrCreate(
            ['device_id' => $deviceId],
            $request->validated(),
        );

        return back()->with('success', 'Información administrativa del WL35 actualizada.');
    }

    public function deleteVideo(
        DeleteWl35VideoRequest $request,
        string $deviceId,
    ): RedirectResponse {
        $this->authorize('create', Device::class);
        $index = (int) $request->validated('index');

        try {
            $this->fleetClient->deleteWl35Video($deviceId, $index);

            return back()->with('success', "Video {$index} eliminado del WL35.");
        } catch (Throwable $exception) {
            Log::error('No fue posible eliminar un video WL35.', [
                'device_id' => $deviceId,
                'video_index' => $index,
                'error' => $exception->getMessage(),
            ]);

            return back()->with('error', $exception->getMessage());
        }
    }

    public function setVolume(
        SetWl35VolumeRequest $request,
        string $deviceId,
    ): RedirectResponse {
        $this->authorize('create', Device::class);
        $volume = (int) $request->validated('volume');

        try {
            $this->fleetClient->setWl35Volume($deviceId, $volume);

            return back()->with('success', "Volumen del WL35 actualizado a {$volume}.");
        } catch (Throwable $exception) {
            Log::error('No fue posible actualizar el volumen WL35.', [
                'device_id' => $deviceId,
                'volume' => $volume,
                'error' => $exception->getMessage(),
            ]);

            return back()->with('error', $exception->getMessage());
        }
    }
}
