<?php

namespace App\Services\Campaigns;

use App\Models\CampaignDevicePlayback;
use App\Services\Fleet\UnifiedFleetClient;
use RuntimeException;

/** Called under the device lock. The durable marker is armed before upload dispatch. */
class CampaignWl35RestartBarrier
{
    public function __construct(private UnifiedFleetClient $fleet) {}

    public function device(string $target): ?array
    {
        return collect($this->fleet->getFleet()['devices'] ?? [])->first(
            fn ($device) => ($device['type'] ?? null) === 'wl35' && ($device['id'] ?? null) === substr($target, 5)
        );
    }

    /** Existing firmware cannot distinguish a network reconnect from a device restart. */
    public function eligibleBoot(?array $device): string
    {
        $boot = $device['boot_id'] ?? null;
        if (! is_string($boot) || $boot === '' || strlen($boot) > 80 || ($device['restart_barrier'] ?? null) !== true) {
            throw new RuntimeException('Actualiza el firmware del equipo 35 para verificar la reconexión antes de publicar.');
        }
        if (($device['online'] ?? false) !== true || ($device['fan_connected'] ?? false) !== true
            || ($device['session_ready'] ?? false) !== true || ($device['status_fresh'] ?? false) !== true
            || ($device['restart_pending'] ?? null) !== false || ($device['uploading'] ?? false) !== false) {
            throw new RuntimeException('El equipo 35 está esperando una conexión y sesión listas antes de publicar.');
        }

        return $boot;
    }

    /** The caller persists this in the same transaction as the upload intent. */
    public function arm(CampaignDevicePlayback $playback, ?array $device): string
    {
        if ($playback->wl35_wait_boot_id) {
            throw new RuntimeException('El equipo 35 está esperando una conexión y sesión listas antes de publicar.');
        }
        $boot = $this->eligibleBoot($device);
        $playback->update(['wl35_wait_boot_id' => $boot, 'wl35_wait_started_at' => now()]);

        return $boot;
    }

    /** A gateway refusal is safe only when it explicitly certifies no command was sent. */
    public function clearCertified(CampaignDevicePlayback $playback, string $boot): void
    {
        CampaignDevicePlayback::whereKey($playback->id)->where('wl35_wait_boot_id', $boot)
            ->update(['wl35_wait_boot_id' => null, 'wl35_wait_started_at' => null]);
    }

    /** Do not clear on disconnection, timeout, gateway restart or network-only reconnect. */
    public function mayProceed(CampaignDevicePlayback $playback): bool
    {
        if (! $playback->wl35_wait_boot_id) {
            return true;
        }
        try {
            $boot = $this->eligibleBoot($this->device($playback->target_key));
        } catch (\Throwable $exception) {
            return false;
        }
        if (hash_equals($playback->wl35_wait_boot_id, $boot)) {
            return false;
        }
        $playback->update(['wl35_wait_boot_id' => null, 'wl35_wait_started_at' => null]);

        return true;
    }
}
