<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkDeviceOperationRequest;
use App\Http\Requests\ChangeDeviceGroupRequest;
use App\Http\Requests\ChangeDeviceLocationRequest;
use App\Http\Requests\StoreDeviceRequest;
use App\Http\Requests\UpdateDeviceRequest;
use App\Models\Campaign;
use App\Models\Device;
use App\Models\Media;
use App\Services\DeviceService;
use App\Services\Z2\Z2DeviceService;
use App\Services\Z2\Z2PlaylistService;
use App\Services\Z2\Z2VideoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

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
     * @var Z2PlaylistService
     */
    protected Z2PlaylistService $z2PlaylistService;

    /**
     * @var Z2VideoService
     */
    protected Z2VideoService $z2VideoService;

    /**
     * DeviceController constructor.
     */
    public function __construct(
        DeviceService $deviceService,
        Z2DeviceService $z2DeviceService,
        Z2PlaylistService $z2PlaylistService,
        Z2VideoService $z2VideoService
    ) {
        $this->deviceService = $deviceService;
        $this->z2DeviceService = $z2DeviceService;
        $this->z2PlaylistService = $z2PlaylistService;
        $this->z2VideoService = $z2VideoService;
    }

    /**
     * Display a listing of devices.
     */
    public function index(): View|RedirectResponse
    {
        $this->authorize('viewAny', Device::class);

        try {
            $this->z2DeviceService->syncDevices();
            $devices = Device::paginate(15);

            return view('devices.index', compact('devices'));
        } catch (\Exception $e) {
            Log::error('Error al listar dispositivos: ' . $e->getMessage());

            return redirect()->route('dashboard.index')
                ->with('error', 'Ocurrió un error al cargar los dispositivos.');
        }
    }

    /**
     * Show the form for creating a new device.
     */
    public function create(): View
    {
        $this->authorize('create', Device::class);

        return view('devices.create');
    }

    /**
     * Store a newly created device.
     */
    public function store(StoreDeviceRequest $request): RedirectResponse
    {
        $this->authorize('create', Device::class);

        try {
            $this->deviceService->create($request->validated());

            return redirect()->route('devices.index')
                ->with('success', 'Dispositivo creado exitosamente.');
        } catch (\Exception $e) {
            Log::error('Error al crear dispositivo: ' . $e->getMessage());

            return back()->with('error', 'Ocurrió un error al crear el dispositivo. Por favor intente nuevamente.');
        }
    }

    /**
     * Display the specified device.
     */
    public function show(Device $device): View
    {
        $this->authorize('view', $device);

        try {
            $deviceDetail = null;
            $devicePlaylist = [];

            if ($device->mac_address) {
                $deviceDetail = $this->z2DeviceService->getDeviceDetail($device->mac_address);
                $devicePlaylist = $this->z2PlaylistService->getDevicePlaylist($device->mac_address);
            }

            // Deduplicated media list for the select dropdown
            $allMediaForDevice = Media::orderBy('name')
                ->get()
                ->unique('file_path')
                ->values();

            return view('devices.show', compact('device', 'deviceDetail', 'devicePlaylist', 'allMediaForDevice'));
        } catch (\Exception $e) {
            Log::error('Error al mostrar dispositivo: ' . $e->getMessage());

            return redirect()->route('devices.index')
                ->with('error', 'Ocurrió un error al cargar el dispositivo.');
        }
    }

    /**
     * Show the form for editing the specified device.
     */
    public function edit(Device $device): View
    {
        $this->authorize('update', $device);

        return view('devices.edit', compact('device'));
    }

    /**
     * Update the specified device.
     */
    public function update(UpdateDeviceRequest $request, Device $device): RedirectResponse
    {
        $this->authorize('update', $device);

        try {
            $this->deviceService->update($device->id, $request->validated());

            return redirect()->route('devices.index')
                ->with('success', 'Dispositivo actualizado exitosamente.');
        } catch (\Exception $e) {
            Log::error('Error al actualizar dispositivo: ' . $e->getMessage());

            return back()->with('error', 'Ocurrió un error al actualizar el dispositivo. Por favor intente nuevamente.');
        }
    }

    /**
     * Remove the specified device.
     */
    public function destroy(Device $device): RedirectResponse
    {
        $this->authorize('delete', $device);

        try {
            // 1. Unbind from Z2 cloud first
            if ($device->mac_address && ! $this->z2DeviceService->unbindDevice($device->mac_address)) {
                return back()->with('error', 'No se pudo eliminar el dispositivo de la nube Z2.');
            }

            // 2. Delete locally
            $this->deviceService->delete($device->id);

            return redirect()->route('devices.index')
                ->with('success', 'Dispositivo eliminado exitosamente.');
        } catch (\Exception $e) {
            Log::error('Error al eliminar dispositivo: ' . $e->getMessage());

            return back()->with('error', 'Ocurrió un error al eliminar el dispositivo. Por favor intente nuevamente.');
        }
    }

    /**
     * Power on the device.
     */
    public function powerOn(Device $device): RedirectResponse
    {
        $this->authorize('update', $device);

        Log::info('[DeviceController] powerOn called', [
            'device_id' => $device->id,
            'mac' => $device->mac_address,
        ]);

        try {
            if (! $device->mac_address) {
                Log::warning('[DeviceController] powerOn - no mac address');
                return back()->with('error', 'El dispositivo no tiene una dirección MAC asignada.');
            }

            Log::info('[DeviceController] Calling z2DeviceService->powerOn', ['mac' => $device->mac_address]);
            $result = $this->z2DeviceService->powerOn($device->mac_address);
            Log::info('[DeviceController] powerOn result', ['result' => $result ? 'true' : 'false']);

            if ($result) {
                // Re-sync device to get updated status after command
                Log::info('[DeviceController] Syncing devices after powerOn');
                $this->z2DeviceService->syncDevices();
                
                return back()->with('success', 'Comando de encendido enviado a la nube. El estado se actualizará en breve.');
            }

            Log::warning('[DeviceController] powerOn failed - z2DeviceService returned false');
            return back()->with('error', 'No se pudo enviar el comando de encendido al dispositivo.');
        } catch (\Exception $e) {
            Log::error('[DeviceController] Error al encender dispositivo: ' . $e->getMessage());

            return back()->with('error', 'Ocurrió un error al encender el dispositivo.');
        }
    }

    /**
     * Power off the device.
     */
    public function powerOff(Device $device): RedirectResponse
    {
        $this->authorize('update', $device);

        Log::info('[DeviceController] powerOff called', [
            'device_id' => $device->id,
            'mac' => $device->mac_address,
        ]);

        try {
            if (! $device->mac_address) {
                Log::warning('[DeviceController] powerOff - no mac address');
                return back()->with('error', 'El dispositivo no tiene una dirección MAC asignada.');
            }

            Log::info('[DeviceController] Calling z2DeviceService->powerOff', ['mac' => $device->mac_address]);
            $result = $this->z2DeviceService->powerOff($device->mac_address);
            Log::info('[DeviceController] powerOff result', ['result' => $result ? 'true' : 'false']);

            if ($result) {
                // Re-sync device to get updated status after command
                Log::info('[DeviceController] Syncing devices after powerOff');
                $this->z2DeviceService->syncDevices();
                
                return back()->with('success', 'Comando de apagado enviado a la nube. El estado se actualizará en breve.');
            }

            Log::warning('[DeviceController] powerOff failed - z2DeviceService returned false');
            return back()->with('error', 'No se pudo enviar el comando de apagado al dispositivo.');
        } catch (\Exception $e) {
            Log::error('[DeviceController] Error al apagar dispositivo: ' . $e->getMessage());

            return back()->with('error', 'Ocurrió un error al apagar el dispositivo.');
        }
    }

    /**
     * Disable the device.
     */
    public function disable(Device $device): RedirectResponse
    {
        $this->authorize('update', $device);

        try {
            $device->update(['status' => 'disabled']);
            Log::info('Dispositivo deshabilitado localmente', ['device_id' => $device->id]);

            return back()->with('success', 'Dispositivo deshabilitado exitosamente.');
        } catch (\Exception $e) {
            Log::error('Error al deshabilitar dispositivo: ' . $e->getMessage());

            return back()->with('error', 'Ocurrió un error al deshabilitar el dispositivo.');
        }
    }

    /**
     * Enable the device.
     */
    public function enable(Device $device): RedirectResponse
    {
        $this->authorize('update', $device);

        try {
            $device->update(['status' => 'active']);
            Log::info('Dispositivo habilitado localmente', ['device_id' => $device->id]);

            return back()->with('success', 'Dispositivo habilitado exitosamente.');
        } catch (\Exception $e) {
            Log::error('Error al habilitar dispositivo: ' . $e->getMessage());

            return back()->with('error', 'Ocurrió un error al habilitar el dispositivo.');
        }
    }

    /**
     * Change the device's group.
     */
    public function changeGroup(ChangeDeviceGroupRequest $request, Device $device): RedirectResponse
    {
        $this->authorize('update', $device);

        try {
            $groupId = $request->validated()['group_id'];

            if ($device->mac_address && $this->z2DeviceService->moveToGroup($device->mac_address, $groupId)) {
                return back()->with('success', 'Grupo del dispositivo actualizado exitosamente.');
            }

            return back()->with('error', 'Ocurrió un error al cambiar el grupo del dispositivo en la nube.');
        } catch (\Exception $e) {
            Log::error('Error al cambiar grupo del dispositivo: ' . $e->getMessage());

            return back()->with('error', 'Ocurrió un error al cambiar el grupo del dispositivo.');
        }
    }

    /**
     * Change the device's location.
     */
    public function changeLocation(ChangeDeviceLocationRequest $request, Device $device): RedirectResponse
    {
        $this->authorize('update', $device);

        try {
            $device->update(['location_id' => $request->validated()['location_id']]);
            Log::info('Ubicación del dispositivo actualizada', ['device_id' => $device->id]);

            return back()->with('success', 'Ubicación del dispositivo actualizada exitosamente.');
        } catch (\Exception $e) {
            Log::error('Error al cambiar ubicación del dispositivo: ' . $e->getMessage());

            return back()->with('error', 'Ocurrió un error al cambiar la ubicación del dispositivo.');
        }
    }

    /**
     * Assign content to the device.
     */
    public function assignContent(BulkDeviceOperationRequest $request, Device $device): RedirectResponse
    {
        $this->authorize('update', $device);

        try {
            $campaignId = $request->input('campaign_id');
            if (! $campaignId) {
                return back()->with('error', 'Se requiere el ID de la campaña.');
            }

            $campaign = Campaign::find($campaignId);
            if (! $campaign) {
                return back()->with('error', 'Campaña no encontrada.');
            }

            $media = $campaign->media()->first();
            if (! $media) {
                return back()->with('error', 'La campaña no tiene medios asociados.');
            }

            if ($device->mac_address && $this->z2DeviceService->changeVideo($device->mac_address, $media->file_path)) {
                $this->deviceService->assignContent($device->id, $campaignId);

                return back()->with('success', 'Contenido asignado al dispositivo exitosamente.');
            }

            return back()->with('error', 'Ocurrió un error al asignar contenido al dispositivo en la nube.');
        } catch (\Exception $e) {
            Log::error('Error al asignar contenido al dispositivo: ' . $e->getMessage());

            return back()->with('error', 'Ocurrió un error al asignar contenido al dispositivo.');
        }
    }

    /**
     * Unbind content from the device.
     */
    public function unbind(Device $device): RedirectResponse
    {
        $this->authorize('update', $device);

        try {
            if ($device->mac_address && $this->z2DeviceService->unbindDevice($device->mac_address)) {
                return back()->with('success', 'Contenido desvinculado del dispositivo exitosamente.');
            }

            return back()->with('error', 'Ocurrió un error al desvincular contenido del dispositivo en la nube.');
        } catch (\Exception $e) {
            Log::error('Error al desvincular contenido del dispositivo: ' . $e->getMessage());

            return back()->with('error', 'Ocurrió un error al desvincular contenido del dispositivo.');
        }
    }

    /**
     * Assign media directly to the device.
     */
    public function assignMedia(Request $request, Device $device): RedirectResponse
    {
        $this->authorize('update', $device);

        $request->validate([
            'media_id' => ['required', 'exists:media,id'],
        ]);

        try {
            if (!$device->mac_address) {
                return back()->with('error', 'El dispositivo no tiene una dirección MAC asignada.');
            }

            $media = Media::find($request->input('media_id'));
            if (!$media) {
                return back()->with('error', 'El medio seleccionado no existe.');
            }

            if ($this->z2DeviceService->changeVideo($device->mac_address, $media->file_path)) {
                Log::info('Contenido asignado directamente al dispositivo', [
                    'device_id' => $device->id,
                    'mac' => $device->mac_address,
                    'media_id' => $media->id,
                    'file_path' => $media->file_path,
                ]);

                return back()->with('success', 'Video asignado directamente al dispositivo exitosamente.');
            }

            return back()->with('error', 'Ocurrió un error al asignar el video al dispositivo en la nube.');
        } catch (\Exception $e) {
            Log::error('Error al asignar video al dispositivo: ' . $e->getMessage());

            return back()->with('error', 'Ocurrió un error al asignar el video al dispositivo.');
        }
    }

    /**
     * Remove a specific video from the device playlist.
     *
     * Uses the Z2 API flow: format SD card (clears all videos) → re-assign
     * only the remaining videos. The device stays bound to the account.
     *
     * POST /User/needFormatSd  — clears SD without unbinding
     * POST /User/upgradeDeviceUi — re-assigns each remaining video
     */
    public function removeMedia(Request $request, Device $device): RedirectResponse
    {
        $this->authorize('update', $device);

        $request->validate([
            'ui_code' => ['required', 'string'],
        ]);

        try {
            if (!$device->mac_address) {
                return back()->with('error', 'El dispositivo no tiene una dirección MAC asignada.');
            }

            $videoToRemove = $request->input('ui_code');

            // Get current playlist (filenames from Z2 cloud)
            $currentPlaylist = $this->z2PlaylistService->getDevicePlaylist($device->mac_address);

            Log::info('removeMedia: playlist actual del dispositivo', [
                'device_id' => $device->id,
                'mac'       => $device->mac_address,
                'playlist'  => $currentPlaylist,
                'removing'  => $videoToRemove,
            ]);

            // Build list of remaining videos
            $remainingFiles = array_values(array_filter(
                $currentPlaylist,
                fn($file) => $file !== $videoToRemove
            ));

            // Step 1: Format the SD card (clears ALL videos, keeps device bound)
            $formatResult = $this->z2DeviceService->formatSd($device->mac_address);
            if (!$formatResult) {
                Log::error('removeMedia: fallo al formatear SD', [
                    'device_id' => $device->id,
                    'mac'       => $device->mac_address,
                ]);
                return back()->with('error', 'No se pudo formatear la tarjeta SD del dispositivo.');
            }

            Log::info('removeMedia: SD formateada, playlist limpia', [
                'device_id' => $device->id,
            ]);

            // If no videos remain, we're done — SD is clean
            if (empty($remainingFiles)) {
                Log::info('removeMedia: no quedan videos tras formatear SD', [
                    'device_id' => $device->id,
                    'mac'       => $device->mac_address,
                ]);
                return back()->with('success', 'Video quitado exitosamente. No quedan videos en el dispositivo.');
            }

            // Small delay to let the device process the format command
            usleep(500000); // 500ms

            // Step 2: Re-assign only the remaining videos
            $reassigned = 0;
            $failedFiles = [];

            foreach ($remainingFiles as $fileName) {
                $uiCode = $this->z2VideoService->getUiCodeByFileName($fileName);
                if (!$uiCode) {
                    Log::warning('removeMedia: no se pudo resolver uiCode, saltando', [
                        'device_id' => $device->id,
                        'file'      => $fileName,
                    ]);
                    $failedFiles[] = $fileName;
                    continue;
                }

                $result = $this->z2DeviceService->changeVideo($device->mac_address, $uiCode);
                if ($result) {
                    $reassigned++;
                    Log::info('removeMedia: video re-asignado al dispositivo', [
                        'device_id' => $device->id,
                        'file'      => $fileName,
                        'uiCode'    => $uiCode,
                    ]);
                } else {
                    $failedFiles[] = $fileName;
                    Log::error('removeMedia: fallo al re-asignar video', [
                        'device_id' => $device->id,
                        'file'      => $fileName,
                        'uiCode'    => $uiCode,
                    ]);
                }
            }

            Log::info('removeMedia: proceso completado', [
                'device_id'    => $device->id,
                'removed'      => $videoToRemove,
                'reassigned'   => $reassigned,
                'failed_count' => count($failedFiles),
                'failed_files' => $failedFiles,
            ]);

            if ($reassigned === 0 && !empty($failedFiles)) {
                return back()->with('warning', 'Video quitado pero no se pudieron re-asignar los videos restantes. El dispositivo quedó sin playlist.');
            }

            if (!empty($failedFiles)) {
                return back()->with('success', "Video quitado exitosamente. Se re-asignaron {$reassigned} videos, pero " . count($failedFiles) . " no se pudieron resolver.");
            }

            return back()->with('success', 'Video quitado del dispositivo exitosamente.');
        } catch (\Exception $e) {
            Log::error('Error al quitar video del dispositivo: ' . $e->getMessage());

            return back()->with('error', 'Ocurrió un error al quitar el video del dispositivo.');
        }
    }
}
