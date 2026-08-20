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
    protected DeviceService $deviceService;

    protected Z2DeviceService $z2DeviceService;

    protected Z2PlaylistService $z2PlaylistService;

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
            Log::error('Error al listar dispositivos: '.$e->getMessage());

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
            Log::error('Error al crear dispositivo: '.$e->getMessage());

            return back()->with('error', 'Ocurrió un error al crear el dispositivo. Por favor intente nuevamente.');
        }
    }

    /**
     * Display the specified device.
     */
    public function show(Device $device): View|RedirectResponse
    {
        $this->authorize('view', $device);

        try {
            $deviceDetail = null;
            $devicePlaylist = [];
            $deviceVolume = null;

            if ($device->mac_address) {
                $deviceDetail = $this->z2DeviceService->getDeviceDetail($device->mac_address);
                $devicePlaylist = $this->z2PlaylistService->getDevicePlaylist($device->mac_address);
                $deviceVolume = $this->z2DeviceService->getVolume($device->mac_address);

                // Read live Bluetooth status from Z2 so the dashboard reflects
                // changes made from the mobile app, then sync the local column.
                $deviceBluetooth = $this->z2DeviceService->getBluetoothStatus($device->mac_address);
                if ($deviceBluetooth !== null && $deviceBluetooth !== $device->bluetooth_status) {
                    $device->update(['bluetooth_status' => $deviceBluetooth]);
                }
            }

            $deviceBluetooth ??= $device->bluetooth_status ?? 'off';

            // Deduplicated media list for the select dropdown
            $allMediaForDevice = Media::orderBy('name')
                ->get()
                ->unique('file_path')
                ->values();

            return view('devices.show', compact('device', 'deviceDetail', 'devicePlaylist', 'allMediaForDevice', 'deviceVolume', 'deviceBluetooth'));
        } catch (\Exception $e) {
            Log::error('Error al mostrar dispositivo: '.$e->getMessage());

            return redirect()->route('devices.index')
                ->with('error', 'Ocurrió un error al cargar el dispositivo.');
        }
    }

    /**
     * Format the SD card of the device.
     */
    public function formatSd(Device $device): RedirectResponse
    {
        $this->authorize('update', $device);

        try {
            if (! $device->mac_address) {
                return back()->with('error', 'El dispositivo no tiene una dirección MAC asignada.');
            }

            if ($this->z2DeviceService->formatSd($device->mac_address)) {
                return back()->with('success', 'Tarjeta SD del dispositivo formateada exitosamente.');
            }

            return back()->with('error', 'No se pudo formatear la tarjeta SD en la nube Z2.');
        } catch (\Exception $e) {
            Log::error('Error al formatear la SD del dispositivo: '.$e->getMessage());

            return back()->with('error', 'Ocurrió un error al formatear la tarjeta SD del dispositivo.');
        }
    }

    /**
     * Set the volume of a device.
     */
    public function setVolume(Request $request, Device $device): RedirectResponse
    {
        $this->authorize('update', $device);

        $request->validate([
            'volume' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        try {
            if (! $device->mac_address) {
                return back()->with('error', 'El dispositivo no tiene una dirección MAC asignada.');
            }

            $volume = (int) $request->input('volume');

            $response = $this->z2DeviceService->setVolume($device->mac_address, $volume);

            if ($response && ($response['result'] ?? -1) === 0) {
                return back()->with('success', "Volumen del dispositivo ajustado a {$volume}%.");
            }

            return back()->with('error', 'No se pudo actualizar el volumen en la nube Z2. '.$this->z2ErrorDetail($response));
        } catch (\Exception $e) {
            Log::error('Error al cambiar volumen del dispositivo: '.$e->getMessage());

            return back()->with('error', 'Ocurrió un error al ajustar el volumen del dispositivo.');
        }
    }

    /**
     * Format SD card for multiple selected devices.
     */
    public function bulkFormatSd(Request $request): RedirectResponse
    {
        $request->validate([
            'device_ids' => ['required', 'array', 'min:1'],
            'device_ids.*' => ['exists:devices,id'],
        ]);

        try {
            $deviceIds = $request->input('device_ids');
            $devices = Device::whereIn('id', $deviceIds)->get();

            $successCount = 0;
            $failCount = 0;

            foreach ($devices as $device) {
                $this->authorize('update', $device);

                if ($device->mac_address && $this->z2DeviceService->formatSd($device->mac_address)) {
                    $successCount++;
                } else {
                    $failCount++;
                }
            }

            if ($failCount === 0) {
                return back()->with('success', "Tarjeta SD formateada exitosamente en los {$successCount} dispositivos seleccionados.");
            }

            if ($successCount > 0) {
                return back()->with('warning', "Se formateó la tarjeta SD en {$successCount} dispositivos, pero falló en {$failCount}.");
            }

            return back()->with('error', 'No se pudo formatear la tarjeta SD en los dispositivos seleccionados.');
        } catch (\Exception $e) {
            Log::error('Error al formatear SDs en lote: '.$e->getMessage());

            return back()->with('error', 'Ocurrió un error al procesar el formateo en lote.');
        }
    }

    /**
     * Execute a supported operation for multiple devices.
     */
    public function bulkOperation(BulkDeviceOperationRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $devices = Device::whereIn('id', $data['device_ids'])->get();
        $successCount = 0;
        $failCount = 0;

        try {
            foreach ($devices as $device) {
                $this->authorize('update', $device);
                $success = match ($data['action']) {
                    'power_on' => $device->mac_address && $this->z2DeviceService->powerOn($device->mac_address),
                    'power_off' => $device->mac_address && $this->z2DeviceService->powerOff($device->mac_address),
                    'disable' => (bool) $device->update(['status' => 'disabled']),
                    'enable' => (bool) $device->update(['status' => 'active']),
                    'change_group' => $this->bulkChangeGroup($device, $data),
                    'change_location' => (bool) $device->update(['location_id' => $data['target_location_id'] ?? null]),
                    'unbind' => $this->z2DeviceService->unbindDevice($device->mac_address ?? ''),
                    default => false,
                };

                $success ? $successCount++ : $failCount++;
            }

            $message = "Acción completada: {$successCount} correctos y {$failCount} fallidos.";

            return $failCount === 0
                ? back()->with('success', $message)
                : back()->with('warning', $message);
        } catch (\Exception $e) {
            Log::error('Error en acción masiva de dispositivos', [
                'action' => $data['action'],
                'exception' => $e,
            ]);

            return back()->with('error', 'Ocurrió un error al ejecutar la acción masiva.');
        }
    }

    /**
     * Change a device group during a bulk operation.
     */
    private function bulkChangeGroup(Device $device, array $data): bool
    {
        if (! isset($data['target_group_id'])) {
            return false;
        }

        if ($device->mac_address && ! $this->z2DeviceService->moveToGroup($device->mac_address, (int) $data['target_group_id'])) {
            return false;
        }

        return (bool) $device->update(['group_id' => $data['target_group_id']]);
    }

    /**
     * Assign a media item to multiple devices at once.
     */
    public function bulkAssignMedia(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'device_ids' => ['required', 'array', 'min:1'],
            'device_ids.*' => ['integer', 'exists:devices,id'],
            'media_id' => ['required', 'exists:media,id'],
        ]);

        $media = Media::find($data['media_id']);
        if (! $media) {
            return back()->with('error', 'El medio seleccionado no existe.');
        }

        $devices = Device::whereIn('id', $data['device_ids'])->get();
        $successCount = 0;
        $failCount = 0;

        try {
            foreach ($devices as $device) {
                $this->authorize('update', $device);

                if (! $device->mac_address) {
                    $failCount++;

                    continue;
                }

                if ($this->z2DeviceService->changeVideo($device->mac_address, $media->file_path)) {
                    $successCount++;
                } else {
                    $failCount++;
                }
            }

            if ($failCount === 0) {
                return back()->with('success', "Medio «{$media->name}» asignado a {$successCount} dispositivos correctamente.");
            }

            if ($successCount > 0) {
                return back()->with('warning', "Medio asignado a {$successCount} dispositivos, pero falló en {$failCount}.");
            }

            return back()->with('error', 'No se pudo asignar el medio a los dispositivos seleccionados.');
        } catch (\Exception $e) {
            Log::error('Error al asignar medio en lote: '.$e->getMessage());

            return back()->with('error', 'Ocurrió un error al asignar el medio en lote.');
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
            Log::error('Error al actualizar dispositivo: '.$e->getMessage());

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
            Log::error('Error al eliminar dispositivo: '.$e->getMessage());

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
            Log::error('[DeviceController] Error al encender dispositivo: '.$e->getMessage());

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
            Log::error('[DeviceController] Error al apagar dispositivo: '.$e->getMessage());

            return back()->with('error', 'Ocurrió un error al apagar el dispositivo.');
        }
    }

    /**
     * Turn the device's Bluetooth on.
     */
    public function bluetoothOn(Device $device): RedirectResponse
    {
        $this->authorize('update', $device);

        try {
            if (! $device->mac_address) {
                return back()->with('error', 'El dispositivo no tiene una dirección MAC asignada.');
            }

            $response = $this->z2DeviceService->bluetoothOn($device->mac_address);

            if ($response && ($response['result'] ?? -1) === 0) {
                $device->update(['bluetooth_status' => 'on']);

                return back()->with('success', 'Comando de Bluetooth enviado. El estado se actualizará en breve.');
            }

            return back()->with('error', 'No se pudo enviar el comando de Bluetooth al dispositivo. '.$this->z2ErrorDetail($response));
        } catch (\Exception $e) {
            Log::error('[DeviceController] Error al activar Bluetooth: '.$e->getMessage());

            return back()->with('error', 'Ocurrió un error al activar el Bluetooth del dispositivo.');
        }
    }

    /**
     * Turn the device's Bluetooth off.
     */
    public function bluetoothOff(Device $device): RedirectResponse
    {
        $this->authorize('update', $device);

        try {
            if (! $device->mac_address) {
                return back()->with('error', 'El dispositivo no tiene una dirección MAC asignada.');
            }

            $response = $this->z2DeviceService->bluetoothOff($device->mac_address);

            if ($response && ($response['result'] ?? -1) === 0) {
                $device->update(['bluetooth_status' => 'off']);

                return back()->with('success', 'Comando de Bluetooth enviado. El estado se actualizará en breve.');
            }

            return back()->with('error', 'No se pudo enviar el comando de Bluetooth al dispositivo. '.$this->z2ErrorDetail($response));
        } catch (\Exception $e) {
            Log::error('[DeviceController] Error al desactivar Bluetooth: '.$e->getMessage());

            return back()->with('error', 'Ocurrió un error al desactivar el Bluetooth del dispositivo.');
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
            Log::error('Error al deshabilitar dispositivo: '.$e->getMessage());

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
            Log::error('Error al habilitar dispositivo: '.$e->getMessage());

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
            Log::error('Error al cambiar grupo del dispositivo: '.$e->getMessage());

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
            Log::error('Error al cambiar ubicación del dispositivo: '.$e->getMessage());

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
            Log::error('Error al asignar contenido al dispositivo: '.$e->getMessage());

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
            Log::error('Error al desvincular contenido del dispositivo: '.$e->getMessage());

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
            if (! $device->mac_address) {
                return back()->with('error', 'El dispositivo no tiene una dirección MAC asignada.');
            }

            $media = Media::find($request->input('media_id'));
            if (! $media) {
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
            Log::error('Error al asignar video al dispositivo: '.$e->getMessage());

            return back()->with('error', 'Ocurrió un error al asignar el video al dispositivo.');
        }
    }

    /**
     * Remove a specific video from the device SD card.
     *
     * The private cloud delivers the removal via heartbeat with FileDelect +
     * PlayList (hardware-verified combo). The video stays in the server
     * library and the rest of the device playlist is untouched.
     */
    public function removeMedia(Request $request, Device $device): RedirectResponse
    {
        $this->authorize('update', $device);

        $request->validate([
            'ui_code' => ['required', 'string'],
        ]);

        try {
            if (! $device->mac_address) {
                return back()->with('error', 'El dispositivo no tiene una dirección MAC asignada.');
            }

            $filename = $request->input('ui_code');

            if ($this->z2DeviceService->removeVideoFromDevice($device->mac_address, $filename)) {
                Log::info('Video quitado del dispositivo', [
                    'device_id' => $device->id,
                    'mac' => $device->mac_address,
                    'filename' => $filename,
                ]);

                return back()->with('success', 'Video quitado del dispositivo exitosamente. El cambio se refleja en el próximo heartbeat.');
            }

            return back()->with('error', 'No se pudo quitar el video del dispositivo.');
        } catch (\Exception $e) {
            Log::error('Error al quitar video del dispositivo: '.$e->getMessage());

            return back()->with('error', 'Ocurrió un error al quitar el video del dispositivo.');
        }
    }

    /**
     * Build a human-readable detail string from a Z2 API response.
     */
    private function z2ErrorDetail(?array $response): string
    {
        if ($response === null) {
            return 'No hubo respuesta del servidor Z2 (revisa conectividad y autenticación).';
        }

        if (isset($response['_error'])) {
            return match ($response['_error']) {
                'invalid_json_response' => 'La API de Z2 devolvió una respuesta NO-JSON (HTTP '.(int) ($response['status_code'] ?? 0).'). Cuerpo: '.(string) ($response['body'] ?? ''),
                'request_exception' => 'Fallo de red/HTTP al llamar a Z2 (HTTP '.(int) ($response['status_code'] ?? 0).'): '.(string) ($response['message'] ?? ''),
                'exception' => 'Excepción al llamar a Z2: '.(string) ($response['message'] ?? ''),
                default => 'Error desconocido de Z2: '.json_encode($response, JSON_UNESCAPED_UNICODE),
            };
        }

        $code = $response['result'] ?? 'n/a';
        $msg = $response['msg'] ?? $response['message'] ?? '';
        $extra = mb_strimwidth(json_encode($response, JSON_UNESCAPED_UNICODE), 0, 300, '...');

        return "Respuesta Z2: result={$code}".($msg ? " msg={$msg}" : '')." {$extra}";
    }
}
