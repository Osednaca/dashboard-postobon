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
use App\Services\GroupCreationService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GroupMembershipUpdateTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    public static function managers(): array
    {
        return [['admin', false], ['superadmin', false], ['admin', true], ['superadmin', true]];
    }

    #[DataProvider('managers')]
    public function test_managers_add_and_move_both_models_without_detaching_current_members_or_changing_settings(string $role, bool $api): void
    {
        $actor = User::factory()->create(['role' => $role]);
        $group = Group::factory()->create(['name' => 'Destino', 'description' => 'Original', 'city' => 'Bogotá']);
        $previous = Group::factory()->create();
        $businessType = BusinessType::create(['name' => 'Tienda']);
        $establishment = Establishment::create(['name' => 'Tienda Centro', 'address' => 'Calle 1', 'business_type_id' => $businessType->id]);
        $current42 = Device::factory()->create(['group_id' => $group->id]);
        $current35 = Wl35DeviceProfile::create(['device_id' => 'current-35', 'name' => 'Actual 35', 'group_id' => $group->id]);
        $device = Device::factory()->create(['group_id' => $previous->id, 'establishment_id' => $establishment->id]);
        $profile = Wl35DeviceProfile::create(['device_id' => 'new-35', 'name' => 'Nuevo 35', 'group_id' => $previous->id,
            'establishment_id' => $establishment->id, 'notes' => 'Conservar configuración']);
        $beforeDevice = Arr::except($device->fresh()->getAttributes(), ['group_id', 'updated_at']);
        $beforeProfile = Arr::except($profile->fresh()->getAttributes(), ['group_id', 'updated_at']);
        $beforeGroup = Arr::except($group->fresh()->getAttributes(), ['name', 'description', 'updated_at']);
        $payload = ['name' => 'Destino actualizado', 'description' => 'Añadidos', 'device_keys' => ['z2:'.$device->id, 'wl35:new-35']];

        $this->actingAs($actor);
        if ($api) {
            $this->putJson('/api/groups/'.$group->id, $payload)->assertOk()->assertJsonPath('name', 'Destino actualizado');
        } else {
            $this->put('/groups/'.$group->id, $payload)->assertRedirect(route('groups.index'))->assertSessionHas('success');
        }

        foreach ([$current42, $current35, $device, $profile] as $member) {
            $this->assertSame($group->id, $member->fresh()->group_id);
        }
        $this->assertEquals($beforeDevice, Arr::except($device->fresh()->getAttributes(), ['group_id', 'updated_at']));
        $this->assertEquals($beforeProfile, Arr::except($profile->fresh()->getAttributes(), ['group_id', 'updated_at']));
        $this->assertEquals($beforeGroup, Arr::except($group->fresh()->getAttributes(), ['name', 'description', 'updated_at']));
        $log = AuditLog::where('entity_type', 'Group')->where('entity_id', $group->id)->sole();
        $this->assertSame($actor->id, $log->user_id);
        $this->assertSame('update', $log->action);
        $this->assertSame('Destino', $log->details['before']['name']);
        $this->assertSame('Destino actualizado', $log->details['after']['name']);
        $this->assertSame([
            ['device_key' => 'z2:'.$device->id, 'previous_group_id' => $previous->id],
            ['device_key' => 'wl35:new-35', 'previous_group_id' => $previous->id],
        ], $log->details['devices']);
        $this->getJson('/api/groups/'.$group->id)->assertOk()->assertJsonPath('devices_count', 2)->assertJsonPath('wl35_devices_count', 2);
        Http::assertNothingSent();
    }

    public function test_add_only_repeated_and_empty_selections_are_idempotent_and_metadata_updates_remain_compatible(): void
    {
        $group = Group::factory()->create(['name' => 'Conservar', 'description' => 'Descripción']);
        $device = Device::factory()->create(['group_id' => null]);
        $profile = Wl35DeviceProfile::create(['device_id' => 'repeat-35', 'name' => 'Repetido']);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $payload = ['device_keys' => ['z2:'.$device->id, 'wl35:repeat-35', 'z2:'.$device->id, 'wl35:repeat-35']];
        $this->patchJson('/api/groups/'.$group->id, $payload)->assertOk()->assertJsonPath('name', 'Conservar');
        $this->patchJson('/api/groups/'.$group->id, $payload)->assertOk();
        $this->putJson('/api/groups/'.$group->id, ['description' => null, 'device_keys' => []])->assertOk();
        $this->putJson('/api/groups/'.$group->id, ['name' => 'Solo nombre'])->assertOk();
        $this->assertSame($group->id, $device->fresh()->group_id);
        $this->assertSame($group->id, $profile->fresh()->group_id);
        $this->assertSame('Solo nombre', $group->fresh()->name);
        $this->assertNull($group->fresh()->description);
        $logs = AuditLog::where('entity_type', 'Group')->where('entity_id', $group->id)->orderBy('id')->get();
        $this->assertCount(2, $logs[0]->details['devices']);
        $this->assertSame([], $logs[1]->details['devices']);
        Http::assertNothingSent();
    }

    public function test_edit_renders_current_members_and_additions_with_move_warning_and_preserves_validation_input(): void
    {
        $group = Group::factory()->create(['name' => 'Grupo Centro']);
        $other = Group::factory()->create(['name' => 'Grupo Norte']);
        $current = Device::factory()->create(['name' => 'Actual 42', 'group_id' => $group->id]);
        Wl35DeviceProfile::create(['device_id' => 'current-ui-35', 'name' => 'Actual 35', 'group_id' => $group->id]);
        $addition = Device::factory()->create(['name' => 'Disponible 42', 'group_id' => null]);
        Wl35DeviceProfile::create(['device_id' => 'other-ui-35', 'name' => 'Otro grupo 35', 'group_id' => $other->id]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $page = $this->get('/groups/'.$group->id.'/edit')->assertOk()->assertSee('Dispositivos actuales (2)')
            ->assertSee('Actual 42')->assertSee('Actual 35')->assertSee('Añadir dispositivos')
            ->assertSee('Al guardar se moverán a Grupo Centro.')->assertSee('device_keys[]', false);
        $page->assertViewHas('currentDevices', fn (array $members) => count($members) === 2);
        $page->assertViewHas('groupDevices', fn (array $devices) => array_column($devices, 'key') === ['z2:'.$addition->id, 'wl35:other-ui-35']);
        $this->from('/groups/'.$group->id.'/edit')->put('/groups/'.$group->id, [
            'name' => '', 'device_keys' => ['z2:'.$addition->id],
        ])->assertRedirect('/groups/'.$group->id.'/edit')->assertSessionHasErrors('name')
            ->assertSessionHasInput('device_keys', ['z2:'.$addition->id]);
        $this->assertSame($group->id, $current->fresh()->group_id);
        $this->assertNull($addition->fresh()->group_id);
        Http::assertNothingSent();
    }

    public function test_unknown_deleted_and_malformed_keys_cannot_partially_update_membership_or_metadata(): void
    {
        $group = Group::factory()->create(['name' => 'Original']);
        $device = Device::factory()->create(['group_id' => null]);
        $deleted = Device::factory()->create();
        $deleted->delete();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        foreach ([['wl35:unknown'], ['z2:'.$deleted->id], ['z2:000'.$device->id], ['unknown:1'], ['z2'], [42], 'not-an-array'] as $keys) {
            $this->putJson('/api/groups/'.$group->id, ['name' => 'Invalid', 'device_keys' => $keys])->assertUnprocessable();
        }
        $this->putJson('/api/groups/'.$group->id, ['device_keys' => ['z2:'.$device->id, 'wl35:unknown']])->assertUnprocessable();
        $this->assertNull($device->fresh()->group_id);
        $this->assertSame('Original', $group->fresh()->name);
        $this->assertSame(0, AuditLog::count());
    }

    public function test_missing_device_during_transaction_rolls_back_an_earlier_addition(): void
    {
        $group = Group::factory()->create(['name' => 'Original']);
        $device = Device::factory()->create(['group_id' => null]);
        try {
            app(GroupCreationService::class)->update($group, [
                'name' => 'Invalid', 'device_keys' => ['z2:'.$device->id, 'wl35:disappeared'],
            ], User::factory()->create(['role' => 'admin'])->id);
            $this->fail('A disappeared member must fail validation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('device_keys', $exception->errors());
        }
        $this->assertNull($device->fresh()->group_id);
        $this->assertSame('Original', $group->fresh()->name);
        $this->assertSame(0, AuditLog::count());
    }

    public function test_audit_failure_rolls_back_metadata_and_both_models(): void
    {
        $group = Group::factory()->create(['name' => 'Original']);
        $old = Group::factory()->create();
        $device = Device::factory()->create(['group_id' => $old->id]);
        $profile = Wl35DeviceProfile::create(['device_id' => 'rollback-update', 'name' => '35', 'group_id' => $old->id]);
        $this->mock(AuditLogService::class)->shouldReceive('log')->once()->andThrow(new \RuntimeException('Audit unavailable'));
        $this->actingAs(User::factory()->create(['role' => 'admin']))->putJson('/api/groups/'.$group->id, [
            'name' => 'Rollback', 'device_keys' => ['z2:'.$device->id, 'wl35:rollback-update'],
        ])->assertServerError();
        $this->assertSame('Original', $group->fresh()->name);
        $this->assertSame($old->id, $device->fresh()->group_id);
        $this->assertSame($old->id, $profile->fresh()->group_id);
    }

    public function test_campaign_and_technician_roles_cannot_edit_or_add_members(): void
    {
        $group = Group::factory()->create();
        $device = Device::factory()->create(['group_id' => null]);
        foreach (['campanas', 'tecnico'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->get('/groups/'.$group->id.'/edit')->assertForbidden();
            $this->put('/groups/'.$group->id, ['device_keys' => ['z2:'.$device->id]])->assertForbidden();
            $this->putJson('/api/groups/'.$group->id, ['device_keys' => ['z2:'.$device->id]])->assertForbidden();
        }
        $this->assertNull($device->fresh()->group_id);
        $this->assertSame(0, AuditLog::count());
        Http::assertNothingSent();
    }
}
