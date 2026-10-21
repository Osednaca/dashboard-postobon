<?php

namespace App\Services\Mobile;

use App\Services\PrivateCloud\PrivateCloudClient;
use App\Support\MobileApi;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class OnboardingTelemetry
{
    public function __construct(private readonly PrivateCloudClient $client) {}

    /** A shared short snapshot avoids full inventory sync or one cloud job per poll. */
    public function find(string $identifier, bool $fresh = false): ?array
    {
        $key = 'mobile:onboarding:telemetry:'.hash('sha256', $this->client->baseUrl());
        $load = fn (): ?array => $this->client->get('/api/devices');
        $snapshot = $fresh ? $load() : Cache::remember($key, max(1, (int) config('mobile_onboarding.snapshot_seconds', 2)), $load);

        if (! is_array($snapshot) || ($snapshot['result'] ?? null) !== 0 || ! is_array($snapshot['devices'] ?? null)) {
            throw new HttpResponseException(MobileApi::error('cloud_unavailable', 'No pudimos verificar la conexión del ventilador. Inténtalo nuevamente.', 503));
        }

        foreach ($snapshot['devices'] as $device) {
            if (is_array($device) && self::normalize((string) ($device['deviceId'] ?? '')) === $identifier) {
                return $device;
            }
        }

        return null;
    }

    public static function normalize(string $identifier): string
    {
        return strtoupper(str_replace([':', '-'], '', trim($identifier)));
    }

    public function seenAt(?array $device): ?Carbon
    {
        try {
            $seen = $device['lastSeenIso'] ?? null;

            return is_string($seen) && $seen !== '' ? Carbon::parse($seen) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
