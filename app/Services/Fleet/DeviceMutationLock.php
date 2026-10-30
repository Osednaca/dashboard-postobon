<?php

namespace App\Services\Fleet;

use App\Models\CampaignDevicePlayback;
use App\Services\CampaignTargetCatalog;
use Illuminate\Support\Facades\Cache;

class DeviceMutationLock
{
    public static function name(string $target): string
    {
        return str_starts_with($target, 'wl35:')
            ? 'device-video-removal:wl35:'.hash('sha256', substr($target, 5))
            : 'campaign-device:'.hash('sha256', CampaignTargetCatalog::z2Key(substr($target, 3)));
    }

    /** Acquire the complete target set before any command or mapping write. */
    public function run(array $targets, callable $operation): mixed
    {
        $names = array_values(array_unique(array_map(self::name(...), $targets)));
        sort($names);
        $locks = [];

        try {
            foreach ($names as $name) {
                // Longer than fleet/campaign job timeouts; never wait while holding another device.
                $lock = Cache::lock($name, 2000);
                if (! $lock->get()) {
                    throw new VideoRemovalRejected('Hay otra operación de contenido en curso para este dispositivo. Espera a que termine.');
                }
                $locks[] = $lock;
            }

            self::assertNoCampaignSession($targets);

            return $operation();
        } finally {
            foreach (array_reverse($locks) as $lock) {
                $lock->release();
            }
        }
    }

    public static function assertNoCampaignSession(array $targets): void
    {
        $targets = array_map(fn ($target) => str_starts_with($target, 'z2:') ? CampaignTargetCatalog::z2Key(substr($target, 3)) : $target, $targets);
        if (CampaignDevicePlayback::whereIn('target_key', $targets)->whereNotNull('baseline')->whereNull('baseline_closed_at')->exists()) {
            throw new VideoRemovalRejected('Este dispositivo conserva una sesión de campaña. Finaliza sus campañas y espera la recuperación del contenido anterior antes de modificarlo.');
        }
    }

    /** Serialize baseline capture with library deletion, which can affect every 42. */
    public static function withBaselineLock(callable $operation): mixed
    {
        $lock = Cache::lock('campaign-baseline-library', 2000);
        if (! $lock->get()) {
            throw new VideoRemovalRejected('Hay otra operación preparando o modificando contenido de campañas. Inténtalo nuevamente.');
        }
        try {
            return $operation();
        } finally {
            $lock->release();
        }
    }
}
