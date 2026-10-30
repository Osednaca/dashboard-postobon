<?php

namespace App\Services\Campaigns;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Shared observations, not health assertions; no device operation runs on a page read. */
class CampaignActivity
{
    private const TICK_KEY = 'campaigns:activity:tick';

    private static function deviceKey(string $target): string
    {
        return 'campaigns:activity:device:'.hash('sha256', $target);
    }

    public function tick(): void
    {
        if (Cache::add('campaigns:activity:tick-throttle', true, 15)) {
            Cache::put(self::TICK_KEY, now()->toIso8601String(), 300);
        }
    }

    public function deviceStarted(string $target): void
    {
        Cache::put(self::deviceKey($target), now()->toIso8601String(), 86400);
    }

    public function lastTick(): ?string
    {
        return Cache::get(self::TICK_KEY);
    }

    public function schedulerFailed(): void
    {
        try {
            $report = Cache::add('campaigns:diagnostic:scheduler-failure', true, 60);
        } catch (Throwable $e) {
            // A broken cache must not hide the failure itself.
            $report = true;
        }
        if ($report) {
            Log::channel('diagnostics')->error('Falló el procesador de campañas; revisa el scheduler y la configuración.');
        }
    }

    public function lastDeviceStart(string $target): ?string
    {
        return Cache::get(self::deviceKey($target));
    }
}
