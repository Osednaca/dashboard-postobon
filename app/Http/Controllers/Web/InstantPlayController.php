<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Device;
use App\Models\Media;
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

    public function __construct(
        Z2DeviceService $z2DeviceService,
        Z2PlaylistService $z2PlaylistService,
        Z2VideoService $z2VideoService
    ) {
        $this->z2DeviceService = $z2DeviceService;
        $this->z2PlaylistService = $z2PlaylistService;
        $this->z2VideoService = $z2VideoService;
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

        // Fetch currently playing media for each device
        $devicesWithPlaying = $devices->map(function (Device $device) {
            $currentPlaying = null;
            if ($device->mac_address) {
                $currentPlaying = $this->z2PlaylistService->getCurrentPlaying($device->mac_address);
            }

            return [
                'device'          => $device,
                'currentPlaying'  => $currentPlaying['displayImageId'] ?? null,
                'playingCount'    => $currentPlaying['playingCount'] ?? '0',
            ];
        });

        // Get all synced Z2 media (file_path is numeric uiCode)
        $allMedia = Media::whereRaw('file_path REGEXP ?', ['^[0-9]+$'])
            ->orderBy('name')
            ->get();

        // Get active campaigns with media
        $campaigns = Campaign::whereIn('status', ['active', 'scheduled', 'draft'])
            ->with('media')
            ->orderBy('name')
            ->get();

        return view('instant-play.index', compact('devicesWithPlaying', 'allMedia', 'campaigns'));
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

            // file_path stores the numeric uiCode for Z2-synced media
            $uiCode = $media->file_path;

            // Verify it's a valid Z2 uiCode (numeric)
            if (!preg_match('/^\d+$/', $uiCode)) {
                return back()->with('error', 'El medio seleccionado no es un video sincronizado con la nube Z2.');
            }

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

            // Resolve uiCode
            $uiCode = $media->file_path;
            if (!preg_match('/^\d+$/', $uiCode)) {
                // Try to resolve from filename
                $resolved = $this->z2VideoService->getUiCodeByFileName($media->name);
                if (!$resolved) {
                    return back()->with('error', 'No se pudo resolver el video de la campaña en la nube Z2.');
                }
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

        if (!preg_match('/^\d+$/', $uiCode)) {
            return back()->with('error', 'El medio seleccionado no es un video sincronizado con la nube Z2.');
        }

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
