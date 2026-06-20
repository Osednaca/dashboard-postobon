<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignMediaRequest;
use App\Http\Requests\ChangeDeviceGroupRequest;
use App\Http\Requests\ChangeDeviceLocationRequest;
use App\Http\Requests\StoreDeviceRequest;
use App\Http\Requests\UpdateDeviceRequest;
use App\Models\Campaign;
use App\Models\Device;
use App\Services\DeviceService;
use App\Services\Z2\Z2DeviceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    /**
     * @var DeviceService
     */
    protected DeviceService $deviceService;

    /**
     * @var Z2DeviceService
     */
    protected Z2DeviceService $z2DeviceService;

    /**
     * DeviceController constructor.
     */
    public function __construct(DeviceService $deviceService, Z2DeviceService $z2DeviceService)
    {
        $this->deviceService = $deviceService;
        $this->z2DeviceService = $z2DeviceService;
    }

    /**
     * Display a paginated listing of devices.
     */
    public function index(): JsonResponse|\Illuminate\Http\RedirectResponse
    {
        if ($redirect = $this->redirectBrowserToWeb('devices.index')) {
            return $redirect;
        }

        try {
            $this->authorize('viewAny', Device::class);
            $this->z2DeviceService->syncDevices();
            $devices = Device::paginate(request()->input('per_page', 15));

            return response()->json($devices);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al listar dispositivos.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created device.
     */
    public function store(StoreDeviceRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', Device::class);
            $device = $this->deviceService->create($request->validated());

            return response()->json($device, 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear el dispositivo.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified device.
     */
    public function show(Device $device): JsonResponse
    {
        try {
            $this->authorize('view', $device);
            $deviceDetail = null;
            if ($device->mac_address) {
                $deviceDetail = $this->z2DeviceService->getDeviceDetail($device->mac_address);
            }

            return response()->json([
                'device' => $device,
                'cloud_detail' => $deviceDetail,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener el dispositivo.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified device.
     */
    public function update(UpdateDeviceRequest $request, Device $device): JsonResponse
    {
        try {
            $this->authorize('update', $device);
            $updated = $this->deviceService->update($device->id, $request->validated());

            return response()->json($updated);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar el dispositivo.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified device.
     */
    public function destroy(Device $device): JsonResponse
    {
        try {
            $this->authorize('delete', $device);
            $this->deviceService->delete($device->id);

            return response()->json([
                'message' => 'Dispositivo eliminado correctamente.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar el dispositivo.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Power on a device.
     */
    public function powerOn(Device $device): JsonResponse
    {
        try {
            $this->authorize('update', $device);

            if ($device->mac_address && $this->z2DeviceService->powerOn($device->mac_address)) {
                return response()->json([
                    'message' => 'Dispositivo encendido correctamente.',
                    'device' => $device->fresh(),
                ]);
            }

            return response()->json([
                'message' => 'Error al encender el dispositivo en la nube.',
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al encender el dispositivo.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Power off a device.
     */
    public function powerOff(Device $device): JsonResponse
    {
        try {
            $this->authorize('update', $device);

            if ($device->mac_address && $this->z2DeviceService->powerOff($device->mac_address)) {
                return response()->json([
                    'message' => 'Dispositivo apagado correctamente.',
                    'device' => $device->fresh(),
                ]);
            }

            return response()->json([
                'message' => 'Error al apagar el dispositivo en la nube.',
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al apagar el dispositivo.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Disable a device.
     */
    public function disable(Device $device): JsonResponse
    {
        try {
            $this->authorize('update', $device);
            $device->update(['status' => 'disabled']);

            return response()->json([
                'message' => 'Dispositivo deshabilitado correctamente.',
                'device' => $device->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al deshabilitar el dispositivo.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Enable a device.
     */
    public function enable(Device $device): JsonResponse
    {
        try {
            $this->authorize('update', $device);
            $device->update(['status' => 'active']);

            return response()->json([
                'message' => 'Dispositivo habilitado correctamente.',
                'device' => $device->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al habilitar el dispositivo.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Change the group of a device.
     */
    public function changeGroup(ChangeDeviceGroupRequest $request, Device $device): JsonResponse
    {
        try {
            $this->authorize('update', $device);

            $updated = $this->z2DeviceService->moveToGroup(
                $device->mac_address,
                $request->validated('group_id')
            );

            if ($updated) {
                return response()->json([
                    'message' => 'Grupo del dispositivo cambiado correctamente.',
                    'device' => $device->fresh(),
                ]);
            }

            return response()->json([
                'message' => 'Error al cambiar el grupo del dispositivo en la nube.',
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al cambiar el grupo del dispositivo.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Change the location of a device.
     */
    public function changeLocation(ChangeDeviceLocationRequest $request, Device $device): JsonResponse
    {
        try {
            $this->authorize('update', $device);
            $device->update(['location_id' => $request->validated('location_id')]);

            return response()->json([
                'message' => 'Ubicación del dispositivo cambiada correctamente.',
                'device' => $device->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al cambiar la ubicación del dispositivo.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Assign content to a device.
     */
    public function assignContent(Request $request, Device $device): JsonResponse
    {
        try {
            $this->authorize('update', $device);

            $request->validate([
                'campaign_id' => ['required', 'integer', 'exists:campaigns,id'],
            ]);

            $campaign = Campaign::find($request->input('campaign_id'));
            if (! $campaign) {
                return response()->json([
                    'message' => 'Campaña no encontrada.',
                ], 404);
            }

            $media = $campaign->media()->first();
            if (! $media) {
                return response()->json([
                    'message' => 'La campaña no tiene medios asociados.',
                ], 422);
            }

            if ($device->mac_address && $this->z2DeviceService->changeVideo($device->mac_address, $media->file_path)) {
                $this->deviceService->assignContent($device->id, $request->input('campaign_id'));
                return response()->json([
                    'message' => 'Contenido asignado correctamente al dispositivo.',
                    'device' => $device->fresh(),
                ]);
            }

            return response()->json([
                'message' => 'Error al asignar contenido al dispositivo en la nube.',
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al asignar contenido al dispositivo.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Unbind content from a device.
     */
    public function unbind(Request $request, Device $device): JsonResponse
    {
        try {
            $this->authorize('update', $device);

            $request->validate([
                'campaign_id' => ['nullable', 'integer', 'exists:campaigns,id'],
            ]);

            if ($device->mac_address && $this->z2DeviceService->unbindDevice($device->mac_address)) {
                $this->deviceService->unbind($device->id, $request->input('campaign_id'));
                return response()->json([
                    'message' => 'Contenido desvinculado correctamente del dispositivo.',
                    'device' => $device->fresh(),
                ]);
            }

            return response()->json([
                'message' => 'Error al desvincular contenido del dispositivo en la nube.',
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al desvincular contenido del dispositivo.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
