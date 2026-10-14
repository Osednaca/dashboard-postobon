<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BusinessType;
use App\Models\Device;
use App\Models\Establishment;
use App\Models\User;
use App\Models\Wl35DeviceProfile;
use App\Services\AuditLogService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DeviceEstablishmentAssignmentTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['unifiedfleet.base_url' => 'http://gateway.test', 'unifiedfleet.token' => 'test-token',
            'unifiedfleet.fallback_base_url' => '']);
        Http::preventStrayRequests();
    }

    private function establishment(string $name): Establishment
    {
        return Establishment::create([
            'business_type_id' => BusinessType::firstOrCreate(['name' => 'Tienda'])->id,
            'name' => $name, 'address' => 'Calle 1',
        ]);
    }

    public static function assignmentRoutes(): array
    {
        return [['superadmin', false], ['tecnico', false], ['superadmin', true], ['tecnico', true]];
    }

    #[DataProvider('assignmentRoutes')]
    public function test_assignment_only_changes_existing_association_and_records_the_actor(string $role, bool $api): void
    {
        $actor = User::factory()->create(['role' => $role]);
        $old = $this->establishment('Anterior');
        $new = $this->establishment('Nueva');
        $device = Device::factory()->create(['establishment_id' => $old->id]);
        $profile = Wl35DeviceProfile::create([
            'device_id' => 'known-35', 'name' => 'Identidad guardada', 'notes' => 'Preservar',
            'establishment_id' => $old->id, 'contact_name' => 'Contacto guardado',
        ]);
        $beforeDevice = Arr::except($device->fresh()->getAttributes(), ['establishment_id', 'updated_at']);
        $beforeProfile = Arr::except($profile->fresh()->getAttributes(), ['establishment_id', 'updated_at']);
        $prefix = $api ? '/api' : '';
        $this->actingAs($actor);
        foreach ([$prefix.'/devices/'.$device->id.'/establishment', $prefix.'/devices/wl35/known-35/establishment'] as $url) {
            $this->patchJson($url, ['establishment_id' => $new->id])->assertOk()
                ->assertJsonPath('success', true)->assertJsonPath('establishment_id', $new->id);
        }
        $this->assertSame($new->id, $device->fresh()->establishment_id);
        $this->assertSame($new->id, $profile->fresh()->establishment_id);
        $this->assertEquals($beforeDevice, Arr::except($device->fresh()->getAttributes(), ['establishment_id', 'updated_at']));
        $this->assertEquals($beforeProfile, Arr::except($profile->fresh()->getAttributes(), ['establishment_id', 'updated_at']));
        $logs = AuditLog::where('action', 'assign_establishment')->get();
        $this->assertCount(2, $logs);
        foreach ($logs as $log) {
            $this->assertSame($actor->id, $log->user_id);
            $this->assertSame($old->id, $log->details['previous_establishment_id']);
            $this->assertSame($new->id, $log->details['establishment_id']);
        }
        Http::assertNothingSent();
    }

    public function test_assignment_rejects_extra_fields_missing_targets_and_invalid_establishments(): void
    {
        $this->actingAs(User::factory()->tecnico()->create());
        $device = Device::factory()->create();
        $establishment = $this->establishment('Destino');
        $profile = Wl35DeviceProfile::create(['device_id' => 'known-35', 'name' => 'Guardado']);
        foreach (['', '/api'] as $prefix) {
            foreach ([$prefix.'/devices/'.$device->id.'/establishment', $prefix.'/devices/wl35/known-35/establishment'] as $url) {
                foreach (['name' => 'Intrusión', 'mac_address' => 'NEW', 'group_id' => 1, 'status' => 'online',
                    'notes' => 'Cambio', 'latitude' => 1, 'device_id' => 'new-35'] as $field => $value) {
                    $this->patchJson($url, ['establishment_id' => $establishment->id, $field => $value])
                        ->assertUnprocessable()->assertJsonValidationErrors($field);
                }
                $this->patchJson($url, [])->assertUnprocessable()->assertJsonValidationErrors('establishment_id');
                $this->patchJson($url, ['establishment_id' => null])->assertUnprocessable();
                $this->patchJson($url, ['establishment_id' => 999999])->assertUnprocessable();
                $this->patchJson($url, ['establishment_id' => []])->assertUnprocessable();
            }
            $this->patchJson($prefix.'/devices/999999/establishment', ['establishment_id' => $establishment->id])->assertNotFound();
        }
        $this->assertNull($device->fresh()->establishment_id);
        $this->assertNull($profile->fresh()->establishment_id);
        $this->assertSame('Guardado', $profile->fresh()->name);
        $this->assertSame(0, AuditLog::where('action', 'assign_establishment')->count());
        Http::assertNothingSent();
    }

    public function test_new_local_35_profile_requires_a_verified_existing_35_device(): void
    {
        Http::fake(['gateway.test/api/fleet' => Http::response(['devices' => [
            ['type' => 'z2', 'id' => 'wrong-type'], ['type' => 'wl35', 'id' => 'verified', 'name' => 'Equipo existente'],
        ]])]);
        $this->actingAs(User::factory()->tecnico()->create());
        $establishment = $this->establishment('Destino');
        $payload = ['establishment_id' => $establishment->id];
        foreach (['', '/api'] as $prefix) {
            $this->patchJson($prefix.'/devices/wl35/wrong-type/establishment', $payload)->assertNotFound();
            $this->patchJson($prefix.'/devices/wl35/unknown/establishment', $payload)->assertNotFound();
        }
        $this->assertSame(0, Wl35DeviceProfile::count());
        $this->patchJson('/devices/wl35/verified/establishment', $payload)->assertOk();
        $profile = Wl35DeviceProfile::sole();
        $this->assertSame('verified', $profile->device_id);
        $this->assertSame($establishment->id, $profile->establishment_id);
        $this->assertSame(0, Device::count());
        Http::assertNotSent(fn ($request) => $request->method() !== 'GET');
    }

    public function test_unavailable_gateway_does_not_fabricate_a_profile(): void
    {
        Http::fake(['gateway.test/api/fleet' => Http::response([], 503)]);
        $this->actingAs(User::factory()->tecnico()->create());
        $payload = ['establishment_id' => $this->establishment('Destino')->id];
        foreach (['', '/api'] as $prefix) {
            $this->patchJson($prefix.'/devices/wl35/unknown/establishment', $payload)->assertStatus(503);
        }
        $this->assertSame(0, Wl35DeviceProfile::count());
        $this->assertSame(0, AuditLog::where('action', 'assign_establishment')->count());
    }

    public function test_disallowed_roles_are_rejected_before_gateway_lookup_or_assignment(): void
    {
        $device = Device::factory()->create();
        $payload = ['establishment_id' => $this->establishment('Destino')->id];
        foreach (['admin', 'campanas'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            foreach (['', '/api'] as $prefix) {
                $this->patchJson($prefix.'/devices/'.$device->id.'/establishment', $payload)->assertForbidden();
                $this->patchJson($prefix.'/devices/wl35/unknown/establishment', $payload)->assertForbidden();
            }
        }
        $this->assertNull($device->fresh()->establishment_id);
        $this->assertSame(0, Wl35DeviceProfile::count());
        Http::assertNothingSent();
    }

    public function test_association_is_rolled_back_when_audit_persistence_fails(): void
    {
        $device = Device::factory()->create();
        $establishment = $this->establishment('Destino');
        $this->mock(AuditLogService::class)->shouldReceive('log')->once()->andThrow(new \RuntimeException('Audit unavailable'));
        $this->actingAs(User::factory()->tecnico()->create())
            ->patchJson('/devices/'.$device->id.'/establishment', ['establishment_id' => $establishment->id])
            ->assertStatus(500);
        $this->assertNull($device->fresh()->establishment_id);
        Http::assertNothingSent();
    }
}
