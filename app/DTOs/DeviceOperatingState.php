<?php

namespace App\DTOs;

final class DeviceOperatingState
{
    /** Normalize reported telemetry without treating a missing value as OFF. */
    public static function fromTelemetry(array $device): array
    {
        $online = self::boolean($device['online'] ?? null);
        $power = self::boolean($device['power'] ?? null);
        $connected = ($device['type'] ?? null) === 'wl35'
            ? self::boolean($device['connected'] ?? null) : $online;
        $status = match (true) {
            $online === false, $connected === false => 'offline',
            $online === null, $connected === null => 'unavailable',
            $power === null => 'unknown',
            $power === false => 'powered_off',
            default => 'online',
        };

        return [
            'status' => $status,
            'online' => $online,
            'connected' => $connected,
            'powered' => $power,
            'bluetooth' => self::boolean($device['bluetooth'] ?? null),
            'sessionReady' => self::boolean($device['session_ready'] ?? null),
            'uploading' => self::boolean($device['uploading'] ?? null),
            'videoCount' => isset($device['video_count']) ? (int) $device['video_count'] : null,
        ];
    }

    private static function boolean(mixed $value): ?bool
    {
        return match ($value) {
            true, 1, '1' => true,
            false, 0, '0' => false,
            default => null,
        };
    }
}
