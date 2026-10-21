<?php

namespace Tests\Feature;

use App\Models\BusinessType;
use App\Models\Device;
use App\Models\Establishment;
use App\Models\User;
use App\Models\Wl35DeviceProfile;
use App\Services\Fleet\UnifiedFleetClient;
use App\Services\PrivateCloud\PrivateCloudClient;
use App\Support\MobileApi;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class MobileDeviceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_safe_unified_list_uses_cached_sources_filters_and_no_sync(): void
    {
        $this->login();
        $establishment = Establishment::create(['name' => 'La Esquina', 'address' => 'Calle 1', 'business_type_id' => BusinessType::create(['name' => 'Tienda'])->id]);
        $device = Device::factory()->create(['mac_address' => 'AABBCCDDEEFF', 'name' => 'Entrada', 'establishment_id' => $establishment->id]);
        Wl35DeviceProfile::create(['device_id' => 'bridge01', 'name' => 'Vitrina', 'establishment_id' => $establishment->id]);
        $this->mock(PrivateCloudClient::class, function ($mock): void {
            $mock->shouldReceive('get')->once()->with('/api/devices')->andReturn(['result' => 0, 'devices' => [[
                'deviceId' => 'AABBCCDDEEFF', 'online' => true, 'lastSeenIso' => now()->toIso8601String(), 'advertisersCode' => 'SECRET', 'ip' => '10.0.0.5',
            ]]]);
        });
        $this->mock(UnifiedFleetClient::class, function ($mock): void {
            $mock->shouldReceive('isConfigured')->once()->andReturn(true);
            $mock->shouldReceive('getFleet')->once()->andReturn(['devices' => [[
                'type' => 'wl35', 'id' => 'bridge01', 'online' => false, 'last_seen' => now()->subHour()->toIso8601String(), 'token' => 'SECRET',
            ]]]);
        });
        $this->getJson('/api/mobile/v1/devices')->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', 'z2:'.$device->id)->assertJsonPath('data.0.connection_status', 'online')
            ->assertJsonPath('data.1.connection_status', 'offline')->assertDontSee('SECRET')->assertDontSee('10.0.0.5');
        $this->getJson('/api/mobile/v1/devices?status=offline&per_page=1')->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.type', 'wl35');
        $this->getJson('/api/mobile/v1/establishments/'.$establishment->id.'/devices?search=Entrada')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.establishment.name', 'La Esquina');
    }

    public function test_failed_or_stale_source_is_unknown_not_offline(): void
    {
        $this->login();
        Device::factory()->create(['mac_address' => 'AABBCCDDEEFF', 'status' => 'online']);
        Wl35DeviceProfile::create(['device_id' => 'bridge01', 'name' => 'Vitrina']);
        $this->mock(PrivateCloudClient::class, fn ($mock) => $mock->shouldReceive('get')->andReturn(null));
        $this->mock(UnifiedFleetClient::class, fn ($mock) => $mock->shouldReceive('isConfigured')->andReturn(false));
        $this->getJson('/api/mobile/v1/devices?status=unknown')->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.status_observed_at', null);
    }

    public function test_stale_positive_and_invalid_dates_are_not_reported_online(): void
    {
        $this->login();
        $this->mock(PrivateCloudClient::class, fn ($mock) => $mock->shouldReceive('get')->andReturn(['result' => 0, 'devices' => [
            ['deviceId' => 'AABBCCDDEEFF', 'online' => true, 'lastSeenIso' => now()->subHour()->toIso8601String()],
            ['deviceId' => '112233445566', 'online' => true, 'lastSeenIso' => 'invalid-date'],
        ]]));
        $this->mock(UnifiedFleetClient::class, fn ($mock) => $mock->shouldReceive('isConfigured')->andReturn(false));
        $this->getJson('/api/mobile/v1/devices?status=online')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_device_mutations_are_absent_and_scope_and_pagination_are_enforced(): void
    {
        $this->login(['mobile:session']);
        $this->getJson('/api/mobile/v1/devices')->assertForbidden();
        $this->postJson('/api/mobile/v1/devices', ['name' => 'no'])->assertStatus(405);
        $this->patchJson('/api/mobile/v1/devices/z2:1', ['name' => 'no'])->assertNotFound();
        $this->login();
        $this->getJson('/api/mobile/v1/devices?per_page=999')->assertUnprocessable();
    }

    private function login(array $abilities = MobileApi::ABILITIES): void
    {
        $user = User::factory()->create(['role' => 'tecnico']);
        $this->withToken($user->createToken('mobile:test', $abilities, now()->addDay())->plainTextToken);
        Auth::forgetGuards();
    }
}
