<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Device;
use App\Models\Media;
use App\Models\Wl35DeviceProfile;
use App\Services\Fleet\UnifiedFleetClient;
use App\Services\Z2\Z2DeviceService;
use App\Services\Z2\Z2PlaylistService;
use App\Services\Z2\Z2VideoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class InstantPlayController extends Controller
{
    protected Z2DeviceService $z2DeviceService;
    protected Z2PlaylistService $z2PlaylistService;
    protected Z2VideoService $z2VideoService;
    protected UnifiedFleetClient $unifiedFleetClient;

    public function __construct(
        Z2DeviceService $z2DeviceService,
        Z2PlaylistService $z2PlaylistService,
        Z2VideoService $z2VideoService,
        UnifiedFleetClient $unifiedFleetClient
    ) {
        $this->z2DeviceService = $z2DeviceService;
        $this->z2PlaylistService = $z2PlaylistService;
        $this->z2VideoService = $z2VideoService;
        $this->unifiedFleetClient = $unifiedFleetClient;
    }

    /**
     * Show the instant play dashboard.
     */
    public function index(): View
    {
        $this->authorize('viewAny', Device::class);

        // Get all active devices with MAC addresses
        $devices = Device::whereNotNull('mac_address')
            ->where('status', '!=', 'disabled')
            ->with('group', 'location')
            ->orderBy('name')
            ->get();

        $gatewayError = null;
        $fleetDevices = collect();

        try {
            $fleetDevices = collect($this->unifiedFleetClient->getFleet()['devices'] ?? []);
        } catch (\Throwable $exception) {
            $gatewayError = $exception->getMessage();
            Log::warning('Los WL35 no pudieron agregarse a reproducción instantánea.', [
                'gateway_url' => $this->unifiedFleetClient->baseUrl(),
                'error' => $exception->getMessage(),
            ]);
        }

        $fleetZ2Devices = $fleetDevices
            ->where('type', 'z2')
            ->keyBy(fn (array $device): string => strtoupper((string) ($device['id'] ?? '')));
        $wl35Profiles = Wl35DeviceProfile::with(['location', 'group'])->get()->keyBy('device_id');

        // Normalize local Z2 devices into the same shape used by gateway WL35 devices.
        $devicesWithPlaying = $devices->map(function (Device $device) use ($fleetZ2Devices) {
            $currentPlaying = null;
            if ($device->mac_address) {
                $currentPlaying = $this->z2PlaylistService->getCurrentPlaying($device->mac_address);
            }

            $mac = strtoupper(str_replace(':', '', (string) $device->mac_address));
            $live = $fleetZ2Devices->get($mac, []);
            $online = $live !== []
                ? (bool) (($live['online'] ?? false) && ($live['connected'] ?? false))
                : in_array($device->status, ['active', 'online'], true);

            return [
                'key' => 'z2:'.$mac,
                'type' => 'z2',
                'name' => $device->name,
                'identifier' => $device->mac_address,
                'online' => $online,
                'status_label' => $online ? 'En línea' : ucfirst((string) $device->status),
                'group' => $device->group?->name,
                'location' => $device->location?->name,
                'current_playing' => $live['current_video'] ?? $currentPlaying['displayImageId'] ?? null,
                'video_count' => (int) ($live['video_count'] ?? 0),
            ];
        });

        $wl35Devices = $fleetDevices
            ->where('type', 'wl35')
            ->map(function (array $device) use ($wl35Profiles): array {
                $online = (bool) (($device['online'] ?? false) && ($device['connected'] ?? false));
                $currentVideo = $device['current_video'] ?? null;
                $profile = $wl35Profiles->get((string) ($device['id'] ?? ''));

                return [
                    'key' => (string) ($device['key'] ?? 'wl35:'.($device['id'] ?? '')),
                    'type' => 'wl35',
                    'name' => (string) ($profile?->name ?: ($device['name'] ?? $device['id'] ?? 'WL35')),
                    'identifier' => (string) ($device['id'] ?? ''),
                    'online' => $online,
                    'status_label' => $online ? 'En línea' : 'Fuera de línea',
                    'group' => $profile?->group?->name,
                    'location' => $profile?->location?->name ?? $device['ip'] ?? null,
                    'current_playing' => $currentVideo !== null ? 'Video '.$currentVideo : null,
                    'video_count' => (int) ($device['video_count'] ?? 0),
                ];
            });

        $liveWl35Ids = $wl35Devices
            ->map(fn (array $device): string => substr($device['key'], strlen('wl35:')));
        $savedOfflineWl35 = $wl35Profiles
            ->reject(fn (Wl35DeviceProfile $profile): bool => $liveWl35Ids->contains($profile->device_id))
            ->map(fn (Wl35DeviceProfile $profile): array => [
                'key' => 'wl35:'.$profile->device_id,
                'type' => 'wl35',
                'name' => $profile->name,
                'identifier' => $profile->device_id,
                'online' => false,
                'status_label' => 'Fuera de línea',
                'group' => $profile->group?->name,
                'location' => $profile->location?->name,
                'current_playing' => null,
                'video_count' => 0,
            ]);

        $wl35Devices = $wl35Devices->concat($savedOfflineWl35);

        $devicesWithPlaying = $devicesWithPlaying
            ->concat($wl35Devices)
            ->sortBy(fn (array $device): string => mb_strtolower($device['name']))
            ->values();

        // Get all synced media (file_path es el filename en la nube privada)
        $allMedia = Media::orderBy('name')
            ->get();

        // Get active campaigns with media
        $campaigns = Campaign::whereIn('status', ['active', 'scheduled', 'draft'])
            ->with('media')
            ->orderBy('name')
            ->get();

        return view('instant-play.index', compact(
            'devicesWithPlaying',
            'allMedia',
            'campaigns',
            'gatewayError'
        ));
    }

    /**
     * Instantly play a specific media on a device.
     */
    public function play(Request $request): RedirectResponse
    {
        $request->validate([
            'device_id' => ['required', 'exists:devices,id'],
            'media_id'  => ['required', 'exists:media,id'],
        ]);

        $device = Device::findOrFail($request->input('device_id'));
        $this->authorize('update', $device);

        $media = Media::findOrFail($request->input('media_id'));

        try {
            if (!$device->mac_address) {
                return back()->with('error', 'El dispositivo no tiene una dirección MAC asignada.');
            }

            // file_path almacena el filename del video en la nube privada
            $uiCode = $media->file_path;

            $success = $this->z2DeviceService->changeVideo($device->mac_address, $uiCode);

            if ($success) {
                Log::info('[InstantPlay] Video enviado al dispositivo', [
                    'device_id'   => $device->id,
                    'device_name' => $device->name,
                    'mac'         => $device->mac_address,
                    'media_id'    => $media->id,
                    'media_name'  => $media->name,
                    'uiCode'      => $uiCode,
                ]);

                return back()->with('success', "Video \"{$media->name}\" enviado a \"{$device->name}\" exitosamente.");
            }

            return back()->with('error', "No se pudo enviar el video al dispositivo \"{$device->name}\".");
        } catch (\Exception $e) {
            Log::error('[InstantPlay] Error: ' . $e->getMessage());
            return back()->with('error', 'Ocurrió un error al enviar el video al dispositivo.');
        }
    }

    /**
     * Play a campaign's media on a device.
     */
    public function playCampaign(Request $request): RedirectResponse
    {
        $request->validate([
            'device_id'   => ['required', 'exists:devices,id'],
            'campaign_id' => ['required', 'exists:campaigns,id'],
        ]);

        $device = Device::findOrFail($request->input('device_id'));
        $this->authorize('update', $device);

        $campaign = Campaign::with('media')->findOrFail($request->input('campaign_id'));

        try {
            if (!$device->mac_address) {
                return back()->with('error', 'El dispositivo no tiene una dirección MAC asignada.');
            }

            // Get the first media from the campaign
            $media = $campaign->media()->first();
            if (!$media) {
                return back()->with('error', "La campaña \"{$campaign->name}\" no tiene medios asociados.");
            }

            // Resolve filename (file_path del media)
            $uiCode = $media->file_path;
            $resolved = $this->z2VideoService->getUiCodeByFileName($media->name);
            if ($resolved) {
                $uiCode = $resolved;
            }

            $success = $this->z2DeviceService->changeVideo($device->mac_address, $uiCode);

            if ($success) {
                Log::info('[InstantPlay] Campaña enviada al dispositivo', [
                    'device_id'     => $device->id,
                    'device_name'   => $device->name,
                    'mac'           => $device->mac_address,
                    'campaign_id'   => $campaign->id,
                    'campaign_name' => $campaign->name,
                    'media_name'    => $media->name,
                    'uiCode'        => $uiCode,
                ]);

                return back()->with('success', "Campaña \"{$campaign->name}\" enviada a \"{$device->name}\" exitosamente.");
            }

            return back()->with('error', "No se pudo enviar la campaña al dispositivo \"{$device->name}\".");
        } catch (\Exception $e) {
            Log::error('[InstantPlay] Campaign error: ' . $e->getMessage());
            return back()->with('error', 'Ocurrió un error al enviar la campaña al dispositivo.');
        }
    }

    /**
     * Play media on multiple devices at once.
     */
    public function playBulk(Request $request): RedirectResponse
    {
        $request->validate([
            'device_ids'   => ['required', 'array', 'min:1'],
            'device_ids.*' => ['exists:devices,id'],
            'media_id'     => ['required', 'exists:media,id'],
        ]);

        $media = Media::findOrFail($request->input('media_id'));
        $deviceIds = $request->input('device_ids');
        $uiCode = $media->file_path;

        $successCount = 0;
        $failCount    = 0;

        foreach ($deviceIds as $deviceId) {
            $device = Device::find($deviceId);
            if (!$device || !$device->mac_address) {
                $failCount++;
                continue;
            }

            try {
                $this->authorize('update', $device);

                if ($this->z2DeviceService->changeVideo($device->mac_address, $uiCode)) {
                    $successCount++;
                    Log::info('[InstantPlay] Bulk play success', [
                        'device_id' => $device->id,
                        'mac'       => $device->mac_address,
                        'media'     => $media->name,
                        'uiCode'    => $uiCode,
                    ]);
                } else {
                    $failCount++;
                }
            } catch (\Exception $e) {
                $failCount++;
                Log::error('[InstantPlay] Bulk play error', [
                    'device_id' => $deviceId,
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        if ($failCount === 0) {
            return back()->with('success', "Video \"{$media->name}\" enviado a {$successCount} dispositivo(s) exitosamente.");
        }

        return back()->with('error', "Enviado a {$successCount} dispositivo(s), falló en {$failCount}.");
    }
}
