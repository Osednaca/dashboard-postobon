<?php

namespace App\Services\Fleet;

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

            return $operation();
        } finally {
            foreach (array_reverse($locks) as $lock) {
                $lock->release();
            }
        }
    }
}
