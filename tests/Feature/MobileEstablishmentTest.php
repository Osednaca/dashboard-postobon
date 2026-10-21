<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BusinessType;
use App\Models\Device;
use App\Models\Establishment;
use App\Models\User;
use App\Models\Wl35DeviceProfile;
use App\Support\MobileApi;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class MobileEstablishmentTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create(['role' => 'tecnico']);
        $this->withToken($user->createToken('mobile:test', MobileApi::ABILITIES, now()->addDay())->plainTextToken);
    }

    public function test_create_read_partial_update_and_delete_do_not_expose_wifi_secret(): void
    {
        $type = BusinessType::create(['name' => 'Tienda']);
        $response = $this->postJson('/api/mobile/v1/establishments', [
            'name' => 'La Esquina', 'address' => 'Calle 1', 'business_type_id' => $type->id,
            'wifi_ssid' => 'Esquina', 'wifi_password' => 'secret-private', 'ignored' => 'not-persisted',
        ])->assertCreated()->assertJsonPath('data.has_wifi_password', true)->assertJsonMissingPath('data.wifi_password');
        $id = $response->json('data.id');
        $establishment = Establishment::findOrFail($id);
        $encrypted = $establishment->getRawOriginal('wifi_password');
        $this->assertNotSame('secret-private', $encrypted);
        $this->getJson('/api/mobile/v1/establishments?search=Esquina')->assertOk()
            ->assertJsonPath('meta.total', 1)->assertDontSee('secret-private');
        $this->getJson('/api/mobile/v1/establishments/'.$id)->assertOk()->assertJsonPath('data.business_type.name', 'Tienda');
        $this->patchJson('/api/mobile/v1/establishments/'.$id, ['name' => 'Nueva Esquina', 'wifi_password' => ''])
            ->assertOk()->assertJsonPath('data.name', 'Nueva Esquina')->assertJsonMissingPath('data.wifi_password');
        $this->assertSame($encrypted, $establishment->refresh()->getRawOriginal('wifi_password'));
        $this->assertStringNotContainsString('secret-private', AuditLog::all()->toJson());
        $this->deleteJson('/api/mobile/v1/establishments/'.$id)->assertNoContent();
        $this->assertModelMissing($establishment);
    }

    public function test_linked_z2_or_wl35_blocks_deletion(): void
    {
        $type = BusinessType::create(['name' => 'Tienda']);
        $z2Establishment = Establishment::create(['name' => 'Z2', 'address' => 'Calle 1', 'business_type_id' => $type->id]);
        $wlEstablishment = Establishment::create(['name' => 'WL', 'address' => 'Calle 2', 'business_type_id' => $type->id]);
        Device::factory()->create(['establishment_id' => $z2Establishment->id]);
        Wl35DeviceProfile::create(['device_id' => 'bridge01', 'name' => 'Vitrina', 'establishment_id' => $wlEstablishment->id]);
        foreach ([$z2Establishment, $wlEstablishment] as $establishment) {
            $this->deleteJson('/api/mobile/v1/establishments/'.$establishment->id)->assertStatus(409)
                ->assertJsonPath('error.code', 'establishment_has_devices');
            $this->assertModelExists($establishment);
        }
    }

    public function test_validation_and_safe_counts_match_web_contract(): void
    {
        $this->postJson('/api/mobile/v1/establishments', ['latitude' => 4.5])->assertUnprocessable()
            ->assertJsonStructure(['error' => ['fields' => ['name', 'address', 'business_type_id', 'longitude']]]);
        $type = BusinessType::create(['name' => 'Tienda']);
        $establishment = Establishment::create(['name' => 'Tienda', 'address' => 'Calle 1', 'business_type_id' => $type->id]);
        $this->patchJson('/api/mobile/v1/establishments/'.$establishment->id, ['name' => ''])
            ->assertUnprocessable();
        $this->getJson('/api/mobile/v1/establishments/'.$establishment->id)->assertOk()
            ->assertJsonPath('data.devices_count', 0)->assertJsonPath('data.wl35_device_profiles_count', 0)
            ->assertJsonPath('data.has_wifi_password', false);
    }
}
