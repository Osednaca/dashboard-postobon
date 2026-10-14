<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeviceLiveStateTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'unifiedfleet.base_url' => 'http://gateway.test', 'unifiedfleet.fallback_base_url' => '',
            'unifiedfleet.token' => 'test-token', 'privatecloud.base_url' => 'http://cloud.test',
            'privatecloud.token' => 'test-token',
        ]);
        Http::preventStrayRequests();
    }

    public function test_operating_state_tracks_telemetry_and_preserves_unknown_values(): void
    {
        Http::fake([
            'gateway.test/api/fleet' => Http::sequence()
                ->push(['devices' => [['type' => 'z2', 'id' => 'AA', 'online' => true, 'power' => true, 'bluetooth' => false]]])
                ->push(['devices' => [['type' => 'z2', 'id' => 'AA', 'online' => true, 'power' => false, 'bluetooth' => true]]])
                ->push(['devices' => [['type' => 'z2', 'id' => 'AA', 'online' => true, 'power' => null]]])
                ->push(['devices' => [['type' => 'wl35', 'id' => 'fan', 'online' => true, 'connected' => false, 'power' => false]]]),
            'cloud.test/api/devices' => Http::response(['devices' => []]),
        ]);
        $this->actingAs(User::factory()->admin()->create());
        $this->getJson(route('devices.previews'))->assertOk()
            ->assertJsonPath('devices.0.operating.status', 'online')->assertJsonPath('devices.0.operating.bluetooth', false);
        $this->getJson(route('devices.previews'))->assertOk()
            ->assertJsonPath('devices.0.operating.status', 'powered_off')->assertJsonPath('devices.0.operating.online', true)
            ->assertJsonPath('devices.0.operating.bluetooth', true);
        $this->getJson(route('devices.previews'))->assertOk()
            ->assertJsonPath('devices.0.operating.status', 'unknown')->assertJsonPath('devices.0.operating.powered', null)
            ->assertJsonPath('devices.0.operating.bluetooth', null);
        $this->getJson(route('devices.previews'))->assertOk()->assertJsonPath('devices.0.operating.status', 'offline');
    }

    public function test_unavailable_providers_do_not_present_saved_power_as_current_state(): void
    {
        Device::factory()->create(['mac_address' => 'AA', 'power_status' => 'on', 'bluetooth_status' => 'on']);
        Http::fake(['*' => Http::response([], 503)]);
        $this->actingAs(User::factory()->admin()->create())->getJson(route('devices.previews'))->assertOk()
            ->assertJsonPath('devices.0.operating.status', 'unavailable')
            ->assertJsonPath('devices.0.operating.powered', null)->assertJsonPath('devices.0.operating.bluetooth', null);
    }

    public function test_z2_commands_return_acceptance_without_claiming_reported_state(): void
    {
        $device = Device::factory()->create(['mac_address' => 'AA', 'bluetooth_status' => 'off']);
        Http::fake([
            'cloud.test/api/devices/AA/power' => Http::response(['result' => 0]),
            'cloud.test/api/devices/AA/bluetooth' => Http::response(['result' => 0]),
            'cloud.test/api/devices' => Http::response(['devices' => []]),
        ]);
        $this->actingAs(User::factory()->admin()->create());
        foreach (['power-on', 'power-off', 'bluetooth-on', 'bluetooth-off'] as $command) {
            $this->postJson(route('devices.'.$command, $device))->assertStatus(202)->assertJsonPath('success', true);
        }
        $this->assertSame('off', $device->fresh()->bluetooth_status);
    }

    public function test_z2_command_failure_and_unauthorized_requests_remain_errors(): void
    {
        $device = Device::factory()->create(['mac_address' => 'AA']);
        $this->postJson(route('devices.power-on', $device))->assertUnauthorized();
        $user = User::factory()->create();
        $user->role = 'viewer';
        $this->actingAs($user)->postJson(route('devices.power-on', $device))->assertForbidden();
        Http::assertNothingSent();
        Http::fake(['cloud.test/*' => Http::response(['result' => -1])]);
        $this->actingAs(User::factory()->admin()->create())->postJson(route('devices.power-on', $device))
            ->assertStatus(502)->assertJsonPath('success', false);
    }

    public function test_fleet_commands_return_json_for_acceptance_partial_failure_and_provider_failure(): void
    {
        Http::fake([
            'gateway.test/api/health' => Http::response(['ok' => true]),
            'gateway.test/api/fleet' => Http::response(['devices' => []]),
            'gateway.test/api/fleet/commands' => Http::sequence()
                ->push(['total' => 2, 'succeeded' => 2, 'failed' => 0])
                ->push(['total' => 2, 'succeeded' => 1, 'failed' => 1])
                ->push([], 503),
        ]);
        $this->actingAs(User::factory()->admin()->create());
        $payload = ['command' => 'power', 'value' => 0, 'targets' => ['wl35:a', 'wl35:b']];
        $this->postJson(route('fleet.command'), $payload)->assertStatus(202)->assertJsonPath('success', true);
        $this->postJson(route('fleet.command'), $payload)->assertStatus(502)->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Orden aceptada por 1 de 2 equipos; 1 fallaron.');
        $this->postJson(route('fleet.command'), $payload)->assertStatus(502)->assertJsonPath('success', false);
    }
}
