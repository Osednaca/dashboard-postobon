<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Services\Fleet\QueueTargetLock;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class QueueTargetLockTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_retired_fan_is_removed_but_other_and_unknown_targets_are_preserved(): void
    {
        $retired = Device::factory()->create(['mac_address' => '90:97:D5:E3:A2:5C']);
        $retired->forceFill(['retired_for_onboarding_at' => now()])->save();
        $retired->delete();
        Device::factory()->create(['mac_address' => '11:22:33:44:55:66']);
        $targets = ['z2:9097d5e3a25c', 'z2:112233445566', 'z2:abcdef123456', 'wl35:abc'];

        $allowed = app(QueueTargetLock::class)->create($targets, fn ($locked) => $locked);

        $this->assertSame(['z2:112233445566', 'z2:abcdef123456', 'wl35:abc'], $allowed);
    }

    public function test_reinstalled_device_accepts_new_work(): void
    {
        $device = Device::factory()->create(['mac_address' => '90:97:D5:E3:A2:5C']);
        $device->forceFill(['retired_for_onboarding_at' => now()])->save();
        $device->delete();
        $device->restore();
        $device->forceFill(['retired_for_onboarding_at' => null])->save();

        $this->assertSame(['z2:9097D5E3A25C'], app(QueueTargetLock::class)->create(['z2:9097D5E3A25C'], fn ($allowed) => $allowed));
    }

    public function test_empty_z2_list_keeps_wl35_and_all_retired_targets_never_run_callback(): void
    {
        $this->assertSame(['wl35:abc'], app(QueueTargetLock::class)->create(['wl35:abc'], fn ($allowed) => $allowed));
        $device = Device::factory()->create(['mac_address' => '90:97:D5:E3:A2:5C']);
        $device->forceFill(['retired_for_onboarding_at' => now()])->save();
        $called = false;
        try {
            app(QueueTargetLock::class)->create(['z2:9097D5E3A25C'], function () use (&$called) {
                $called = true;
            });
            $this->fail('Expected unavailable targets to fail');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('ya no están disponibles', $exception->getMessage());
        }
        $this->assertFalse($called);
    }
}
