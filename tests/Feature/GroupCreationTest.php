<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BusinessType;
use App\Models\Device;
use App\Models\Establishment;
use App\Models\Group;
use App\Models\User;
use App\Models\Wl35DeviceProfile;
use App\Services\AuditLogService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GroupCreationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['services.google_maps.key' => '']);
    }

    public static function creationRoles(): array
    {
        return [['superadmin', false], ['admin', false], ['campanas', false],
            ['superadmin', true], ['admin', true], ['campanas', true]];
    }

    #[DataProvider('creationRoles')]
    public function test_creating_a_group_assigns_both_models_atomically_without_changing_establishment_or_settings(string $role, bool $api): void
    {
        $actor = User::factory()->create(['role' => $role]);
        $businessType = BusinessType::create(['name' => 'Oxxo']);
        $establishment = Establishment::create(['name' => 'Oxxo Centro', 'business_type_id' => $businessType->id, 'address' => 'Calle 1']);
        $oldGroup = Group::factory()->create();
        $device = Device::factory()->create(['group_id' => $oldGroup->id, 'establishment_id' => $establishment->id]);
        $profile = Wl35DeviceProfile::create(['device_id' => 'screen-35', 'name' => 'Pantalla 35', 'group_id' => $oldGroup->id,
            'establishment_id' => $establishment->id, 'notes' => 'Conservar']);
        $beforeDevice = Arr::except($device->fresh()->getAttributes(), ['group_id', 'updated_at']);
        $beforeProfile = Arr::except($profile->fresh()->getAttributes(), ['group_id', 'updated_at']);
        $this->actingAs($actor);
        $payload = ['name' => 'Medellín', 'description' => 'Zona urbana', 'business_type_id' => $businessType->id,
            'address' => 'Medellín, Antioquia', 'city' => 'Medellín', 'latitude' => 6.2442, 'longitude' => -75.5812,
            'google_place_id' => 'test-place', 'device_keys' => ['z2:'.$device->id, 'wl35:screen-35']];
        if ($api) {
            $this->postJson('/api/groups', $payload)->assertCreated()->assertJsonPath('description', 'Zona urbana');
        } else {
            $this->post('/groups', $payload)->assertSessionHas('success')->assertRedirect();
        }
        $group = Group::where('name', 'Medellín')->firstOrFail();
        $this->assertSame($businessType->id, $group->business_type_id);
        $this->assertSame('6.24420000', $group->latitude);
        $this->assertSame($group->id, $device->fresh()->group_id);
        $this->assertSame($group->id, $profile->fresh()->group_id);
        $this->assertEquals($beforeDevice, Arr::except($device->fresh()->getAttributes(), ['group_id', 'updated_at']));
        $this->assertEquals($beforeProfile, Arr::except($profile->fresh()->getAttributes(), ['group_id', 'updated_at']));
        $log = AuditLog::where('entity_type', 'Group')->where('entity_id', $group->id)->firstOrFail();
        $this->assertSame($actor->id, $log->user_id);
        $this->assertCount(2, $log->details['devices']);
        $this->assertSame($oldGroup->id, $log->details['devices'][0]['previous_group_id']);
        $this->getJson('/api/groups/'.$group->id)->assertOk()->assertJsonPath('devices_count', 1)->assertJsonPath('wl35_devices_count', 1);
        Http::assertNothingSent();
    }

    public function test_group_metadata_and_membership_are_optional(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'campanas']))
            ->postJson('/api/groups', ['name' => 'Sin clasificación'])->assertCreated();
        $group = Group::where('name', 'Sin clasificación')->firstOrFail();
        $this->assertNull($group->business_type_id);
        $this->assertNull($group->address);
        $this->assertSame(0, $group->devices()->count() + $group->wl35Devices()->count());
    }

    public function test_invalid_members_and_metadata_are_rejected_and_validation_input_is_retained(): void
    {
        $device = Device::factory()->create();
        $this->actingAs(User::factory()->create(['role' => 'campanas']));
        foreach ([['device_keys' => ['wl35:unknown']], ['device_keys' => ['z2:'.$device->id, 'z2:'.$device->id]],
            ['business_type_id' => 99999], ['latitude' => 6.2], ['latitude' => 91, 'longitude' => 0],
            ['latitude' => 0, 'longitude' => 181]] as $invalid) {
            $this->postJson('/api/groups', ['name' => 'No guardar'] + $invalid)->assertUnprocessable();
        }
        $this->assertSame(0, Group::where('name', 'No guardar')->count());
        $this->from('/groups/create')->post('/groups', ['name' => '', 'city' => 'Medellín', 'device_keys' => ['z2:'.$device->id]])
            ->assertRedirect('/groups/create')->assertSessionHasErrors('name')
            ->assertSessionHasInput('city', 'Medellín')->assertSessionHasInput('device_keys', ['z2:'.$device->id]);
        $device->delete();
        $this->postJson('/api/groups', ['name' => 'Borrado', 'device_keys' => ['z2:'.$device->id]])->assertUnprocessable();
    }

    public function test_failed_audit_rolls_back_group_and_all_memberships(): void
    {
        $old = Group::factory()->create();
        $device = Device::factory()->create(['group_id' => $old->id]);
        $profile = Wl35DeviceProfile::create(['device_id' => 'rollback-35', 'name' => '35', 'group_id' => $old->id]);
        $this->mock(AuditLogService::class)->shouldReceive('log')->once()->andThrow(new \RuntimeException('Audit unavailable'));
        $this->actingAs(User::factory()->create(['role' => 'campanas']))->postJson('/api/groups', [
            'name' => 'Rollback', 'device_keys' => ['z2:'.$device->id, 'wl35:rollback-35'],
        ])->assertServerError();
        $this->assertSame(0, Group::where('name', 'Rollback')->count());
        $this->assertSame($old->id, $device->fresh()->group_id);
        $this->assertSame($old->id, $profile->fresh()->group_id);
    }

    public function test_group_pages_show_both_models_and_respect_role_links_and_actions(): void
    {
        $group = Group::factory()->create(['name' => 'Medellín', 'city' => 'Medellín', 'address' => 'Antioquia']);
        $device = Device::factory()->create(['name' => 'Pantalla Centro', 'group_id' => $group->id]);
        Wl35DeviceProfile::create(['device_id' => 'info-35', 'name' => 'Pantalla Norte', 'group_id' => $group->id]);
        foreach (['admin', 'campanas'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->get('/groups')->assertOk()->assertSee('Medellín');
            $page = $this->get('/groups/'.$group->id)->assertOk()->assertSee('Pantalla Centro')->assertSee('Pantalla Norte');
            if ($role === 'admin') {
                $page->assertDontSee('href="'.route('devices.show', $device).'"', false);
            } else {
                $page->assertDontSee('action="'.route('groups.power-on', $group).'"', false)
                    ->assertDontSee('href="'.route('groups.edit', $group).'"', false)
                    ->assertDontSee('action="'.route('groups.destroy', $group).'"', false);
            }
            $this->get('/groups/create')->assertOk()->assertSee('groupDeviceSelector')->assertSee('Medellín')
                ->assertSee('name="latitude"', false)->assertSee('name="longitude"', false);
        }
        $this->actingAs(User::factory()->create(['role' => 'tecnico']))->postJson('/api/groups', ['name' => 'No'])->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_deleting_a_group_releases_both_models(): void
    {
        $group = Group::factory()->create();
        $device = Device::factory()->create(['group_id' => $group->id]);
        $profile = Wl35DeviceProfile::create(['device_id' => 'delete-35', 'name' => '35', 'group_id' => $group->id]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->deleteJson('/api/groups/'.$group->id)->assertOk();
        $this->assertSoftDeleted($group);
        $this->assertNull($device->fresh()->group_id);
        $this->assertNull($profile->fresh()->group_id);
    }
}
