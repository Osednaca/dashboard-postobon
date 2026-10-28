<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BusinessType;
use App\Models\Campaign;
use App\Models\Device;
use App\Models\DeviceHeartbeat;
use App\Models\Establishment;
use App\Models\Group;
use App\Models\Media;
use App\Models\Schedule;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\Z2\Z2CampaignSyncService;
use App\Services\Z2\Z2DeviceService;
use App\Services\Z2\Z2PlaylistService;
use App\Support\MobileApi;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeviceRetirementTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const MAC = '9097D5E3A25C';

    private array $telemetry = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->startOfSecond());
        config(['privatecloud.base_url' => 'http://private-cloud.test', 'mobile_onboarding.enabled' => true]);
        Http::fake(fn () => Http::response(['result' => 0, 'devices' => $this->telemetry]));
    }

    private function establishment(string $name): Establishment
    {
        return Establishment::create(['name' => $name, 'address' => 'Calle 1',
            'business_type_id' => BusinessType::firstOrCreate(['name' => 'Tienda'])->id]);
    }

    private function heartbeat(int $count): void
    {
        $this->telemetry = [['deviceId' => self::MAC, 'online' => true, 'heartbeatCount' => $count,
            'lastSeenIso' => now()->toIso8601String(), 'playMode' => 1, 'deviceClass' => 6]];
        Cache::flush();
    }

    public function test_retirement_sync_and_fresh_verified_onboarding_reuse_device_and_preserve_history(): void
    {
        $old = $this->establishment('Anterior');
        $new = $this->establishment('Nueva');
        $group = Group::factory()->create();
        $device = Device::factory()->create(['mac_address' => self::MAC, 'name' => 'Anterior',
            'establishment_id' => $old->id, 'group_id' => $group->id]);
        $other = Device::factory()->create(['mac_address' => 'AABBCCDDEE01', 'establishment_id' => $old->id, 'group_id' => $group->id]);
        $heartbeat = DeviceHeartbeat::create(['device_id' => $device->id, 'rpm' => 200, 'status' => 'online', 'received_at' => now()]);
        $admin = User::factory()->superadmin()->create();
        $schedule = Schedule::create(['name' => 'Anterior', 'type' => 'power_on', 'status' => 'pending',
            'scheduled_at' => now()->addHour(), 'device_id' => $device->id, 'group_id' => null]);
        $campaign = Campaign::create(['name' => 'Anterior', 'status' => 'draft', 'created_by' => $admin->id,
            'target_devices' => ['z2:'.self::MAC, 'z2:AABBCCDDEE01']]);

        $this->actingAs($admin)->delete(route('devices.destroy', $device))->assertRedirect(route('devices.index'));
        $retired = Device::withTrashed()->findOrFail($device->id);
        $this->assertTrue($retired->trashed());
        $this->assertNotNull($retired->retired_for_onboarding_at);
        $this->assertNull($retired->establishment_id);
        $this->assertNull($retired->group_id);
        $this->assertSame($old->id, $other->fresh()->establishment_id);
        $this->assertSame($group->id, $other->fresh()->group_id);
        $this->assertDatabaseHas('device_heartbeats', ['id' => $heartbeat->id, 'device_id' => $device->id]);
        $this->assertDatabaseHas('device_retirement_exclusions', ['device_id' => $device->id, 'scope' => 'schedule', 'resource_id' => (string) $schedule->id]);
        $this->assertDatabaseHas('device_retirement_exclusions', ['device_id' => $device->id, 'scope' => 'campaign', 'resource_id' => (string) $campaign->id]);
        $this->assertSame(['z2:'.self::MAC, 'z2:AABBCCDDEE01'], $campaign->fresh()->target_devices);
        $this->assertSame(1, AuditLog::where('action', 'device_retired_for_reinstallation')->where('user_id', $admin->id)->count());

        $this->heartbeat(10);
        $this->telemetry[] = ['deviceId' => $other->mac_address, 'name' => 'Nube', 'online' => true];
        app(Z2DeviceService::class)->syncDevices();
        $this->assertTrue(Device::withTrashed()->findOrFail($device->id)->trashed());
        $this->assertSame(1, Device::withTrashed()->where('mac_address', self::MAC)->count());
        $this->assertSame($old->id, $other->fresh()->establishment_id);

        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $technician = User::factory()->tecnico()->create();
        $this->withToken($technician->createToken('mobile:test', MobileApi::ABILITIES, now()->addDay())->plainTextToken);
        $base = '/api/mobile/v1/device-onboardings';
        $uuid = $this->withHeader('Idempotency-Key', 'retire-cycle-0001')->postJson($base, [
            'device_type' => 'z2', 'expected_identifier' => null, 'name' => 'Instalación nueva',
            'establishment_id' => $new->id, 'play_mode' => 1, 'sync_class' => 6,
        ])->assertCreated()->json('data.id');
        $this->postJson($base.'/'.$uuid.'/register', ['expected_identifier' => self::MAC])->assertOk();
        $this->postJson($base.'/'.$uuid.'/complete')->assertConflict();
        $this->assertTrue(Device::withTrashed()->findOrFail($device->id)->trashed());
        $this->travel(1)->seconds();
        $this->heartbeat(11);
        $this->postJson($base.'/'.$uuid.'/complete')->assertOk()->assertJsonPath('data.status', 'completed');
        $restored = Device::findOrFail($device->id);
        $this->assertSame('Instalación nueva', $restored->name);
        $this->assertSame($new->id, $restored->establishment_id);
        $this->assertNull($restored->group_id);
        $this->assertDatabaseHas('device_heartbeats', ['id' => $heartbeat->id, 'device_id' => $device->id]);
        $this->assertDatabaseHas('device_retirement_exclusions', ['device_id' => $device->id, 'scope' => 'schedule', 'resource_id' => (string) $schedule->id]);
    }

    public function test_unauthorized_roles_cannot_retire_and_api_retirement_uses_the_same_rules(): void
    {
        $device = Device::factory()->create(['mac_address' => self::MAC, 'establishment_id' => $this->establishment('Tienda')->id]);
        foreach (['tecnico', 'campanas', 'admin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->deleteJson(route('devices.destroy', $device))->assertForbidden();
            $this->deleteJson('/api/devices/'.$device->id)->assertForbidden();
        }
        $this->assertNotNull($device->fresh()->establishment_id);
        $this->actingAs(User::factory()->superadmin()->create())->deleteJson('/api/devices/'.$device->id)->assertOk();
        $this->assertTrue(Device::withTrashed()->findOrFail($device->id)->trashed());
        $this->assertNotNull(Device::withTrashed()->findOrFail($device->id)->retired_for_onboarding_at);
        Http::assertNothingSent();
    }

    public function test_audit_failure_rolls_back_retirement_and_exclusions(): void
    {
        $establishment = $this->establishment('Tienda');
        $device = Device::factory()->create(['mac_address' => self::MAC, 'establishment_id' => $establishment->id]);
        $schedule = Schedule::create(['name' => 'Anterior', 'type' => 'power_on', 'status' => 'pending',
            'scheduled_at' => now()->addHour(), 'device_id' => $device->id, 'group_id' => null]);
        $this->mock(AuditLogService::class)->shouldReceive('log')->once()->andThrow(new \RuntimeException('intentional audit failure'));
        $this->actingAs(User::factory()->superadmin()->create())->delete(route('devices.destroy', $device))->assertRedirect();
        $fresh = Device::withTrashed()->findOrFail($device->id);
        $this->assertFalse($fresh->trashed());
        $this->assertSame($establishment->id, $fresh->establishment_id);
        $this->assertNull($fresh->retired_for_onboarding_at);
        $this->assertDatabaseMissing('device_retirement_exclusions', ['device_id' => $device->id, 'scope' => 'schedule', 'resource_id' => (string) $schedule->id]);
        Http::assertNothingSent();
    }

    public function test_preexisting_device_group_and_all_schedules_exclude_reinstalled_fan_but_new_schedules_include_it(): void
    {
        $group = Group::factory()->create();
        $device = Device::factory()->create(['mac_address' => self::MAC, 'group_id' => $group->id]);
        $other = Device::factory()->create(['mac_address' => 'AABBCCDDEE01', 'group_id' => $group->id]);
        $base = ['name' => 'Apagar', 'type' => 'power_off', 'status' => 'pending',
            'scheduled_at' => now()->subMinute(), 'recurrence_type' => 'once'];
        $direct = Schedule::create($base + ['target_scope' => 'device', 'device_id' => $device->id]);
        $groupSchedule = Schedule::create($base + ['target_scope' => 'group', 'group_id' => $group->id]);
        $all = Schedule::create($base + ['target_scope' => 'all']);

        $this->actingAs(User::factory()->superadmin()->create())->delete(route('devices.destroy', $device))->assertRedirect();
        // Completing onboarding restores this same row; explicitly rejoining a group must not revive old schedules.
        $reinstalled = Device::withTrashed()->findOrFail($device->id);
        $reinstalled->restore();
        $reinstalled->forceFill(['retired_for_onboarding_at' => null, 'group_id' => $group->id])->save();
        $new = Schedule::create($base + ['target_scope' => 'all']);

        $service = $this->mock(Z2DeviceService::class);
        $service->shouldReceive('powerOff')->times(3)->with($other->mac_address)->andReturnTrue();
        $service->shouldReceive('powerOff')->once()->with($device->mac_address)->andReturnTrue();
        $this->artisan('schedules:process')->assertSuccessful();
        $this->assertSame('failed', $direct->fresh()->status);
        $this->assertSame('executed', $groupSchedule->fresh()->status);
        $this->assertSame('executed', $all->fresh()->status);
        $this->assertSame('executed', $new->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_old_campaigns_exclude_the_retired_identity_without_changing_other_recipients_or_history(): void
    {
        $admin = User::factory()->superadmin()->create();
        $group = Group::factory()->create();
        $device = Device::factory()->create(['mac_address' => self::MAC, 'group_id' => $group->id]);
        $other = Device::factory()->create(['mac_address' => 'AABBCCDDEE01', 'group_id' => $group->id]);
        $media = Media::factory()->video()->create();
        $legacy = Campaign::create(['name' => 'Anterior', 'status' => 'draft', 'created_by' => $admin->id]);
        $legacy->media()->attach($media->id);
        $explicit = Campaign::create(['name' => 'Lista anterior', 'status' => 'draft', 'created_by' => $admin->id,
            'target_devices' => ['z2:'.self::MAC, 'z2:AABBCCDDEE01']]);
        $this->actingAs($admin)->delete(route('devices.destroy', $device))->assertRedirect();
        $service = app(Z2CampaignSyncService::class);
        // Even while the retired fan is absent from inventory, other historical recipients remain valid.
        $service->validateRecipients($explicit);
        $this->assertSame(['z2:'.self::MAC, 'z2:AABBCCDDEE01'], $explicit->fresh()->target_devices);

        $reinstalled = Device::withTrashed()->findOrFail($device->id);
        $reinstalled->restore();
        $reinstalled->forceFill(['retired_for_onboarding_at' => null, 'group_id' => $group->id])->save();
        $new = Campaign::create(['name' => 'Nueva', 'status' => 'draft', 'created_by' => $admin->id]);
        $new->media()->attach($media->id);
        $playlist = $this->mock(Z2PlaylistService::class);
        $playlist->shouldReceive('assignVideoToDevice')->twice()
            ->with($other->mac_address, $media->file_path)->andReturnTrue();
        $playlist->shouldReceive('assignVideoToDevice')->once()
            ->with($device->mac_address, $media->file_path)->andReturnTrue();
        $this->app->forgetInstance(Z2CampaignSyncService::class);
        $service = app(Z2CampaignSyncService::class);
        $this->assertTrue($service->publishToDevices($legacy, [$device->id, $other->id]));
        $this->assertTrue($service->publishToGroups($legacy, [$group->id]));
        $this->assertTrue($service->publishToDevices($new, [$device->id]));
        Http::assertNothingSent();
    }

    public function test_ordinary_soft_deleted_device_is_not_eligible_for_onboarding(): void
    {
        $device = Device::factory()->create(['mac_address' => self::MAC]);
        $device->delete();
        $user = User::factory()->tecnico()->create();
        $this->withToken($user->createToken('mobile:test', MobileApi::ABILITIES, now()->addDay())->plainTextToken);
        $base = '/api/mobile/v1/device-onboardings';
        $uuid = $this->withHeader('Idempotency-Key', 'old-delete-0001')->postJson($base, [
            'device_type' => 'z2', 'expected_identifier' => null, 'name' => 'Intento',
            'establishment_id' => $this->establishment('Tienda')->id, 'play_mode' => 0, 'sync_class' => 0,
        ])->assertCreated()->json('data.id');
        $this->postJson($base.'/'.$uuid.'/register', ['expected_identifier' => self::MAC])->assertConflict()
            ->assertJsonPath('error.code', 'device_already_assigned');
        $this->assertTrue(Device::withTrashed()->findOrFail($device->id)->trashed());
    }

    public function test_retirement_migration_rollback_refuses_to_discard_records(): void
    {
        $device = Device::factory()->create(['mac_address' => self::MAC]);
        $this->actingAs(User::factory()->superadmin()->create())->delete(route('devices.destroy', $device))->assertRedirect();
        $migration = require database_path('migrations/2026_10_28_000002_add_device_reinstallation_retirement.php');
        try {
            $migration->down();
            $this->fail('The rollback must refuse to delete retirement state.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('retirement records exist', $exception->getMessage());
        }
        $retired = Device::withTrashed()->findOrFail($device->id);
        $this->assertTrue($retired->trashed());
        $this->assertNotNull($retired->retired_for_onboarding_at);
    }
}
