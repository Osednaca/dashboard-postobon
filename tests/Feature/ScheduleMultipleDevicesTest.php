<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Media;
use App\Models\Schedule;
use App\Models\User;
use App\Models\Wl35DeviceProfile;
use App\Services\Fleet\FleetMediaPlaybackService;
use App\Services\Fleet\UnifiedFleetClient;
use App\Services\Z2\Z2DeviceService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ScheduleMultipleDevicesTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Storage::fake('local');
        $this->actingAs(User::factory()->superadmin()->create());
    }

    private function payload(array $extra = []): array
    {
        return array_replace(['name' => 'Selección', 'type' => 'power_off', 'target_scope' => 'device',
            'recurrence_type' => 'once', 'scheduled_at' => now()->subMinute()->format('Y-m-d H:i:s')], $extra);
    }

    public function test_create_edit_and_partial_api_update_preserve_selected_devices(): void
    {
        $a = Device::factory()->create(['name' => 'Equipo 42']);
        $b = Wl35DeviceProfile::create(['name' => 'Equipo 35', 'device_id' => 'fan-b']);
        $targets = ['z2:'.$a->id, 'wl35:'.$b->id];

        $this->get('/schedules/create')->assertOk()->assertSee('Equipo 42')->assertSee('Equipo 35');
        $this->post('/schedules', $this->payload(['device_targets' => $targets]))
            ->assertRedirect()->assertSessionHasNoErrors();
        $schedule = Schedule::sole();
        $this->assertSame($targets, $schedule->device_targets);
        $this->assertNull($schedule->device_id);
        $this->assertSame('device', $schedule->controlTargetScope());
        $this->get('/schedules/'.$schedule->id.'/edit')->assertOk()
            ->assertSee('value="z2:'.$a->id.'"', false)->assertSee('value="wl35:'.$b->id.'"', false);
        $this->putJson('/api/schedules/'.$schedule->id, ['name' => 'Renombrada'])
            ->assertOk()->assertJsonPath('device_targets', $targets);
        $this->put('/schedules/'.$schedule->id, $this->payload(['device_targets' => ['wl35:'.$b->id]]))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(['wl35:'.$b->id], $schedule->fresh()->device_targets);
        $this->from('/schedules/'.$schedule->id.'/edit')
            ->put('/schedules/'.$schedule->id, $this->payload(['device_targets_present' => '1']))
            ->assertSessionHasErrors('device_targets');
        $this->assertSame(['wl35:'.$b->id], $schedule->fresh()->device_targets);
        $this->putJson('/api/schedules/'.$schedule->id, ['target_scope' => 'all'])
            ->assertOk()->assertJsonPath('device_targets', null);
    }

    public function test_invalid_empty_conflicting_or_deleted_selections_are_rejected(): void
    {
        $device = Device::factory()->create();
        foreach ([[], ['wl35:999999'], ['bogus:1'], ['z2:'.$device->id, 'z2:'.$device->id]] as $targets) {
            $this->postJson('/api/schedules', $this->payload(['device_targets' => $targets]))->assertUnprocessable();
        }
        $this->postJson('/api/schedules', $this->payload(['target_scope' => 'all', 'device_targets' => ['z2:'.$device->id]]))
            ->assertUnprocessable();
        $this->postJson('/api/schedules', $this->payload(['device_id' => $device->id, 'device_targets' => ['z2:'.$device->id]]))
            ->assertUnprocessable();
        $device->delete();
        $this->postJson('/api/schedules', $this->payload(['device_targets' => ['z2:'.$device->id]]))->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_edit_keeps_missing_selection_visible_until_it_is_explicitly_removed(): void
    {
        $present = Device::factory()->create(['name' => 'Disponible']);
        $removed = Device::factory()->create(['name' => 'Eliminado']);
        $media = Media::forceCreate(['name' => 'Video', 'file_path' => 'video.mp4', 'original_name' => 'video.mp4', 'mime_type' => 'video/mp4', 'size' => 100]);
        $targets = ['z2:'.$present->id, 'z2:'.$removed->id];
        $schedule = Schedule::create($this->payload(['type' => 'change_content', 'target_scope' => null,
            'content_id' => $media->id, 'device_targets' => $targets]) + ['status' => 'pending']);
        $removed->delete();

        $this->get('/schedules/'.$schedule->id.'/edit')->assertOk()
            ->assertSee("scope: 'device'", false)
            ->assertSee('Dispositivo no disponible (z2:'.$removed->id.')')
            ->assertSee('value="z2:'.$removed->id.'" :disabled="scope !== \'device\'" checked', false);
        $this->from('/schedules/'.$schedule->id.'/edit')->put('/schedules/'.$schedule->id,
            $this->payload(['type' => 'change_content', 'content_id' => $media->id, 'device_targets' => $targets]))
            ->assertSessionHasErrors('device_targets.1');
        $this->assertSame($targets, $schedule->fresh()->device_targets);
        $this->put('/schedules/'.$schedule->id, $this->payload(['type' => 'change_content', 'content_id' => $media->id,
            'device_targets_present' => '1', 'device_targets' => ['z2:'.$present->id]]))
            ->assertSessionHasNoErrors();
        $this->assertSame(['z2:'.$present->id], $schedule->fresh()->device_targets);
    }

    public function test_two_selected_devices_of_same_model_do_not_reach_a_third_device(): void
    {
        $a = Device::factory()->create(['mac_address' => 'AABB']);
        $b = Device::factory()->create(['mac_address' => 'CCDD']);
        Device::factory()->create(['mac_address' => 'EEEE']);
        $schedule = Schedule::create($this->payload(['device_targets' => ['z2:'.$a->id, 'z2:'.$b->id]]) + ['status' => 'pending']);
        $service = $this->mock(Z2DeviceService::class);
        $service->shouldReceive('powerOff')->once()->with('AABB')->andReturnTrue();
        $service->shouldReceive('powerOff')->once()->with('CCDD')->andReturnTrue();
        $this->artisan('schedules:process')->assertSuccessful();
        $this->assertSame('executed', $schedule->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_content_with_partially_missing_selection_does_not_reach_outsiders(): void
    {
        $selected = Device::factory()->create(['mac_address' => 'AABB']);
        $removed = Device::factory()->create(['mac_address' => 'CCDD']);
        Device::factory()->create(['mac_address' => 'EEEE']);
        $media = Media::forceCreate(['name' => 'Video', 'file_path' => 'video.mp4', 'original_name' => 'video.mp4', 'mime_type' => 'video/mp4', 'size' => 100]);
        $schedule = Schedule::create($this->payload(['type' => 'change_content', 'content_id' => $media->id,
            'device_targets' => ['z2:'.$selected->id, 'z2:'.$removed->id]]) + ['status' => 'pending']);
        $removed->delete();
        $this->mock(Z2DeviceService::class)->shouldReceive('changeVideoOnDevices')->once()
            ->with(['AABB'], 'video.mp4')->andReturn(['results' => ['AABB' => true]]);
        $this->artisan('schedules:process')->assertSuccessful();
        $this->assertSame('failed', $schedule->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_control_targets_only_selected_mixed_devices_and_missing_target_fails(): void
    {
        $selected = Device::factory()->create(['mac_address' => 'AABB']);
        Device::factory()->create(['mac_address' => 'CCDD']);
        $profile = Wl35DeviceProfile::create(['name' => 'Selected', 'device_id' => 'selected']);
        Wl35DeviceProfile::create(['name' => 'Outside', 'device_id' => 'outside']);
        $schedule = Schedule::create($this->payload(['device_targets' => ['z2:'.$selected->id, 'wl35:'.$profile->id]]) + ['status' => 'pending']);
        $this->mock(Z2DeviceService::class)->shouldReceive('powerOff')->once()->with('AABB')->andReturnTrue();
        $this->mock(UnifiedFleetClient::class)->shouldReceive('sendCommand')->once()
            ->with(['command' => 'power', 'targets' => ['wl35:selected'], 'value' => false], false)
            ->andReturn(['results' => [['key' => 'wl35:selected', 'type' => 'wl35', 'id' => 'selected', 'success' => true]]]);
        $this->artisan('schedules:process')->assertSuccessful();
        $this->assertSame('executed', $schedule->fresh()->status);

        $profile->delete();
        $schedule->refresh()->update(['device_targets' => ['wl35:'.$profile->id], 'status' => 'pending']);
        $this->artisan('schedules:process')->assertSuccessful();
        $this->assertSame('failed', $schedule->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_content_targets_only_selected_mixed_devices(): void
    {
        $selected = Device::factory()->create(['mac_address' => 'AABB']);
        Device::factory()->create(['mac_address' => 'CCDD']);
        $profile = Wl35DeviceProfile::create(['name' => 'Selected', 'device_id' => 'selected']);
        Wl35DeviceProfile::create(['name' => 'Outside', 'device_id' => 'outside']);
        $media = Media::forceCreate(['name' => 'Video', 'file_path' => 'video.mp4', 'original_name' => 'video.mp4',
            'mime_type' => 'video/mp4', 'duration' => 20, 'size' => 100]);
        $schedule = Schedule::create($this->payload(['type' => 'change_content', 'content_id' => $media->id,
            'device_targets' => ['z2:'.$selected->id, 'wl35:'.$profile->id]]) + ['status' => 'pending']);
        $this->mock(Z2DeviceService::class)->shouldReceive('changeVideoOnDevices')->once()
            ->with(['AABB'], 'video.mp4')->andReturn(['results' => ['AABB' => true]]);
        $this->mock(FleetMediaPlaybackService::class)->shouldReceive('play')->once()
            ->withArgs(fn ($item, $targets) => $item->id === $media->id && $targets === ['wl35:selected'])
            ->andReturn(['success' => true]);
        $this->artisan('schedules:process')->assertSuccessful();
        $this->assertSame('executed', $schedule->fresh()->status);
        Http::assertNothingSent();
    }
}
