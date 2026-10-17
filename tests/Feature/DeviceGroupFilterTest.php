<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Group;
use App\Models\User;
use App\Models\Wl35DeviceProfile;
use App\Services\Fleet\UnifiedFleetClient;
use App\Services\Z2\Z2DeviceService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeviceGroupFilterTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function fleet(array $devices = [], bool $unavailable = false): void
    {
        $this->mock(Z2DeviceService::class)->shouldReceive('syncDevices')->andReturn([]);
        $client = $this->mock(UnifiedFleetClient::class);
        $client->shouldReceive('isConfigured')->andReturn(true);
        $client->shouldReceive('baseUrl')->andReturn('http://gateway.test');
        if ($unavailable) {
            $client->shouldReceive('getFleet')->andThrow(new \RuntimeException('Gateway unavailable'));
        } else {
            $client->shouldReceive('getFleet')->andReturn(['devices' => $devices]);
        }
    }

    public function test_group_filter_precedes_pagination_and_includes_only_its_live_and_offline_35(): void
    {
        $this->actingAs(User::factory()->superadmin()->create());
        $group = Group::factory()->create(['name' => 'Grupo objetivo']);
        $other = Group::factory()->create(['name' => 'Otro grupo']);
        Device::factory()->count(17)->create(['group_id' => $other->id]);
        $matching = Device::factory()->count(16)->create(['group_id' => $group->id]);
        Wl35DeviceProfile::create(['device_id' => 'live', 'name' => 'Local live', 'group_id' => $group->id]);
        Wl35DeviceProfile::create(['device_id' => 'offline', 'name' => 'Local offline', 'group_id' => $group->id]);
        Wl35DeviceProfile::create(['device_id' => 'other', 'name' => 'Other', 'group_id' => $other->id]);
        $this->fleet([
            ['type' => 'wl35', 'id' => 'live', 'name' => 'Live'],
            ['type' => 'wl35', 'id' => 'other', 'name' => 'Other'],
            ['type' => 'wl35', 'id' => 'unregistered', 'name' => 'Unknown'],
        ]);
        $first = $this->get(route('devices.index', ['group_id' => $group->id]))->assertOk()
            ->assertSee('Grupo objetivo')->assertSee('Quitar video')->assertSee('El archivo seguirá disponible en Medios.');
        $this->assertSame(16, $first->viewData('devices')->total());
        $this->assertCount(15, $first->viewData('devices'));
        $this->assertSame([$group->id], $first->viewData('devices')->pluck('group_id')->unique()->values()->all());
        $this->assertEqualsCanonicalizing(['live', 'offline'], $first->viewData('wl35Devices')->pluck('id')->all());
        $this->assertStringContainsString('group_id='.$group->id, $first->viewData('devices')->nextPageUrl());
        $second = $this->get($first->viewData('devices')->nextPageUrl())->assertOk();
        $this->assertCount(1, $second->viewData('devices'));
        $this->assertContains($second->viewData('devices')->first()->id, $matching->modelKeys());
        Http::assertNothingSent();
    }

    public function test_group_filter_applies_to_saved_profiles_when_gateway_is_unavailable(): void
    {
        $this->actingAs(User::factory()->superadmin()->create());
        $group = Group::factory()->create();
        Wl35DeviceProfile::create(['device_id' => 'in-group', 'name' => 'Dentro', 'group_id' => $group->id]);
        Wl35DeviceProfile::create(['device_id' => 'ungrouped', 'name' => 'Fuera']);
        $this->fleet([], true);
        $response = $this->get(route('devices.index', ['group_id' => $group->id]))->assertOk();
        $this->assertSame(['in-group'], $response->viewData('wl35Devices')->pluck('id')->all());
        $all = $this->get(route('devices.index'))->assertOk();
        $this->assertCount(2, $all->viewData('wl35Devices'));
        Http::assertNothingSent();
    }

    public function test_invalid_or_deleted_groups_fail_before_remote_reads(): void
    {
        $this->actingAs(User::factory()->superadmin()->create());
        $deleted = Group::factory()->create();
        $deleted->delete();
        $this->mock(Z2DeviceService::class)->shouldNotReceive('syncDevices');
        $this->mock(UnifiedFleetClient::class)->shouldNotReceive('getFleet');
        foreach (['abc', '999999', (string) $deleted->id, ['nested']] as $value) {
            $this->getJson(route('devices.index', ['group_id' => $value]))
                ->assertUnprocessable()->assertJsonValidationErrors('group_id');
        }
        Http::assertNothingSent();
    }

    public function test_readers_can_filter_without_receiving_removal_controls(): void
    {
        $group = Group::factory()->create();
        Device::factory()->create(['group_id' => $group->id]);
        $this->fleet();
        foreach (['tecnico', 'campanas'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->get(route('devices.index', ['group_id' => $group->id]))->assertOk()
                ->assertSee('name="group_id"', false)->assertSee('Filtrar grupo')
                ->assertDontSee('Quitar video')->assertDontSee('devices/bulk-remove-video');
        }
        Http::assertNothingSent();
    }
}
