<?php

namespace Tests\Feature;

use App\Models\BusinessType;
use App\Models\Device;
use App\Models\Establishment;
use App\Models\User;
use App\Services\Z2\Z2DeviceService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeviceNameTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'privatecloud.base_url' => 'http://cloud.test', 'privatecloud.token' => 'test-token',
            'unifiedfleet.base_url' => 'http://gateway.test', 'unifiedfleet.token' => 'test-token',
            'unifiedfleet.fallback_base_url' => '',
        ]);
        Http::preventStrayRequests();
    }

    private function establishment(): Establishment
    {
        return Establishment::create([
            'business_type_id' => BusinessType::firstOrCreate(['name' => 'Tienda de prueba'])->id,
            'name' => 'Tienda Centro', 'address' => 'Calle 1',
        ]);
    }

    private function telemetry(array $devices): void
    {
        Http::fake([
            'cloud.test/api/devices' => Http::response(['result' => 0, 'devices' => $devices]),
            'cloud.test/api/devices/*' => Http::response(['result' => 0, 'device' => [
                'name' => 'Nombre nube', 'online' => true, 'power' => 1,
                'playlist' => [], 'volume' => 50, 'btSwitch' => 1,
            ]]),
            'gateway.test/api/fleet' => Http::response(['devices' => [[
                'type' => 'z2', 'id' => 'AABBCCDDEEFF', 'name' => 'Nombre gateway',
                'online' => true, 'power' => true, 'current_video' => null,
            ]], 'media' => []]),
        ]);
    }

    public function test_web_rename_survives_list_and_repeated_sync_and_labels_use_local_name(): void
    {
        $establishment = $this->establishment();
        $device = Device::factory()->create([
            'mac_address' => 'AABBCCDDEEFF', 'name' => 'Nombre nube',
            'status' => 'offline', 'firmware' => 'old', 'establishment_id' => $establishment->id,
        ]);
        $this->telemetry([[
            'deviceId' => $device->mac_address, 'name' => 'Nombre nube', 'online' => true,
            'power' => 1, 'btSwitch' => 1, 'speed' => 900, 'version' => 'new',
            'hardVersion' => 'Z2-Pro', 'lastSeenIso' => '2026-10-10T12:00:00Z',
        ]]);
        $this->actingAs(User::factory()->admin()->create())->put(route('devices.update', $device), [
            'name' => 'Entrada principal', 'establishment_id' => $establishment->id,
        ])->assertRedirect(route('devices.index'))->assertSessionHasNoErrors();
        $this->assertSame('Entrada principal', $device->fresh()->name);
        $this->get(route('devices.index'))->assertOk()->assertSee('Entrada principal');
        app(Z2DeviceService::class)->syncDevices();

        $device->refresh();
        $this->assertSame('Entrada principal', $device->name);
        $this->assertSame('online', $device->status);
        $this->assertSame('new', $device->firmware);
        $this->assertSame('Z2-Pro', $device->hardware);
        $this->assertSame(900.0, (float) $device->rpm);
        $this->assertSame('on', $device->power_status);
        $this->assertSame('on', $device->bluetooth_status);
        $this->assertSame('2026-10-10 12:00:00', $device->last_heartbeat_at->toDateTimeString());
        $this->assertSame($establishment->id, $device->establishment_id);
        $this->get(route('devices.show', $device))->assertOk()->assertSee('Entrada principal');
        $this->getJson(route('devices.previews'))->assertOk()->assertJsonPath('devices.0.name', 'Entrada principal');
    }

    public function test_new_devices_use_cloud_names_or_mac_fallback_and_restored_devices_keep_local_name(): void
    {
        $restored = Device::factory()->create(['mac_address' => 'RESTORED', 'name' => 'Nombre guardado']);
        $restored->delete();
        $this->telemetry([
            ['deviceId' => 'NEW', 'name' => 'Nombre inicial'],
            ['deviceId' => 'NO-NAME'],
            ['deviceId' => 'MAC-NAME', 'name' => 'MAC-NAME'],
            ['deviceId' => 'RESTORED', 'name' => 'Nombre nube'],
        ]);

        app(Z2DeviceService::class)->syncDevices();
        $this->assertSame('Nombre inicial', Device::where('mac_address', 'NEW')->firstOrFail()->name);
        $this->assertSame('Device NO-NAME', Device::where('mac_address', 'NO-NAME')->firstOrFail()->name);
        $this->assertSame('Device MAC-NAME', Device::where('mac_address', 'MAC-NAME')->firstOrFail()->name);
        $this->assertSame('Nombre guardado', $restored->fresh()->name);
        $this->assertFalse($restored->fresh()->trashed());
    }

    public function test_invalid_or_unauthorized_rename_does_not_change_device(): void
    {
        $device = Device::factory()->create(['name' => 'Nombre guardado']);
        $payload = ['name' => 'No autorizado', 'establishment_id' => $this->establishment()->id];
        $this->putJson(route('devices.update', $device), $payload)->assertUnauthorized();
        $viewer = User::factory()->create();
        $viewer->role = 'viewer';
        $this->actingAs($viewer)->putJson(route('devices.update', $device), $payload)->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())
            ->putJson(route('devices.update', $device), array_replace($payload, ['name' => str_repeat('x', 256)]))
            ->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->assertSame('Nombre guardado', $device->fresh()->name);
        Http::assertNothingSent();
    }
}
