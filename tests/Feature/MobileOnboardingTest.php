<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BusinessType;
use App\Models\Device;
use App\Models\DeviceOnboarding;
use App\Models\Establishment;
use App\Models\User;
use App\Services\AuditLogService;
use App\Support\MobileApi;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MobileOnboardingTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const BASE = '/api/mobile/v1/device-onboardings';

    private const MAC = 'AABBCCDDEEFF';

    private User $actor;

    private Establishment $establishment;

    private array $devices = [];

    private bool $cloudUnavailable = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->startOfSecond());
        config([
            'privatecloud.base_url' => 'http://private-cloud.test',
            'privatecloud.token' => 'server-only-secret',
            'mobile_onboarding.enabled' => true,
            'mobile_onboarding.cloud_domain' => 'devices.example.test:9920',
            'mobile_onboarding.requires_bind' => false,
        ]);
        Http::fake(fn () => Http::response(
            $this->cloudUnavailable ? [] : ['result' => 0, 'devices' => $this->devices],
            $this->cloudUnavailable ? 503 : 200,
        ));
        $this->actor = User::factory()->tecnico()->create();
        $this->authenticate($this->actor);
        $this->establishment = Establishment::create([
            'name' => 'Tienda de prueba', 'address' => 'Calle 1',
            'business_type_id' => BusinessType::firstOrCreate(['name' => 'Tienda'])->id,
            'wifi_password' => 'not-for-mobile',
        ]);
    }

    private function authenticate(User $user, ?array $abilities = null): void
    {
        $this->app['auth']->forgetGuards();
        $token = $user->createToken('mobile:test', $abilities ?? MobileApi::ABILITIES, now()->addDay())->plainTextToken;
        $this->withToken($token);
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'device_type' => 'z2', 'expected_identifier' => self::MAC,
            'name' => 'Entrada principal', 'establishment_id' => $this->establishment->id,
            'play_mode' => 1, 'sync_class' => 6,
        ], $overrides);
    }

    private function prepare(string $key = 'operation-0001', array $overrides = []): string
    {
        return $this->withHeader('Idempotency-Key', $key)->postJson(self::BASE, $this->payload($overrides))
            ->assertCreated()->json('data.id');
    }

    private function report(string $uuid): void
    {
        $this->postJson(self::BASE.'/'.$uuid.'/events', ['event' => 'configuration_sent', 'result_code' => 'readback_verified'])
            ->assertOk()->assertJsonPath('data.status', 'awaiting_online');
    }

    private function heartbeat(int $count = 1, array $overrides = []): void
    {
        $this->devices = [array_replace([
            'deviceId' => self::MAC, 'online' => true, 'heartbeatCount' => $count,
            'lastSeenIso' => now()->toIso8601String(), 'playMode' => 1, 'deviceClass' => 6,
        ], $overrides)];
        Cache::flush();
    }

    public function test_prepares_owner_scoped_idempotent_session_without_secrets_or_inventory_mutation(): void
    {
        $uuid = $this->prepare(overrides: ['expected_identifier' => 'aa:bb:cc:dd:ee:ff']);
        $this->withHeader('Idempotency-Key', 'operation-0001')->postJson(self::BASE, $this->payload())
            ->assertCreated()->assertJsonPath('data.id', $uuid)
            ->assertJsonPath('data.sync_class', 6)->assertJsonPath('data.play_mode', 1)
            ->assertJsonPath('data.provisioning_profile.tcp_port', 9910)
            ->assertJsonMissingPath('data.baseline_heartbeat_count')
            ->assertJsonMissingPath('data.request_hash');
        $this->assertSame(1, DeviceOnboarding::count());
        $this->assertSame(0, Device::count());
        $this->assertStringNotContainsString('server-only-secret', $this->getJson(self::BASE.'/'.$uuid)->getContent());
        $this->assertStringNotContainsString('not-for-mobile', DeviceOnboarding::sole()->toJson());
        $this->assertSame(1, AuditLog::where('action', 'mobile_onboarding_prepared')->count());
    }

    public function test_reused_key_with_different_input_or_active_second_installer_conflicts(): void
    {
        $this->prepare();
        $this->postJson(self::BASE, $this->payload(['sync_class' => 5]))
            ->assertConflict()->assertJsonPath('error.code', 'idempotency_conflict');
        $this->authenticate(User::factory()->tecnico()->create());
        $this->withHeader('Idempotency-Key', 'another-operation')->postJson(self::BASE, $this->payload())
            ->assertConflict()->assertJsonPath('error.code', 'onboarding_in_progress');
        $this->assertSame(1, DeviceOnboarding::count());
    }

    public function test_disabled_invalid_or_privileged_binding_profile_fails_closed(): void
    {
        foreach ([
            ['mobile_onboarding.enabled' => false],
            ['mobile_onboarding.enabled' => true, 'mobile_onboarding.cloud_domain' => 'https://dashboard.example.test'],
            ['mobile_onboarding.cloud_domain' => 'devices.example.test:9920', 'mobile_onboarding.requires_bind' => true,
                'mobile_onboarding.advertisers_code' => 'privileged-secret', 'mobile_onboarding.public_enrollment_identifier' => false],
        ] as $settings) {
            config($settings);
            $this->withHeader('Idempotency-Key', 'operation-profile')->postJson(self::BASE, $this->payload())
                ->assertStatus(503)->assertJsonPath('error.code', 'onboarding_unconfigured');
        }
        $this->assertSame(0, DeviceOnboarding::count());
        Http::assertNothingSent();
    }

    public function test_validated_public_enrollment_identifier_is_only_exposed_when_required(): void
    {
        config(['mobile_onboarding.requires_bind' => true, 'mobile_onboarding.public_enrollment_identifier' => true,
            'mobile_onboarding.advertisers_code' => 'public-installation-id']);
        $this->withHeader('Idempotency-Key', 'operation-bind')->postJson(self::BASE, $this->payload())
            ->assertCreated()->assertJsonPath('data.provisioning_profile.advertisers_code', 'public-installation-id');
    }

    public function test_invalid_class_mode_identity_name_and_secret_fields_are_rejected(): void
    {
        foreach ([
            ['sync_class' => 15], ['sync_class' => -1], ['play_mode' => 2],
            ['play_mode' => 0, 'sync_class' => 6], ['expected_identifier' => 'not-a-mac'],
            ['name' => 'Entrada&Domain=evil'], ['name' => str_repeat('🎈', 40)],
            ['wifi_password' => 'do-not-store'], ['cloud_domain' => 'evil.test:9910'],
        ] as $overrides) {
            $this->withHeader('Idempotency-Key', 'operation-invalid')->postJson(self::BASE, $this->payload($overrides))
                ->assertUnprocessable();
        }
        $this->assertSame(0, DeviceOnboarding::count());
        Http::assertNothingSent();
    }

    public function test_actor_role_and_token_ability_are_enforced_and_other_owner_is_hidden(): void
    {
        $uuid = $this->prepare();
        $this->authenticate(User::factory()->tecnico()->create());
        $this->getJson(self::BASE.'/'.$uuid)->assertNotFound();
        $this->postJson(self::BASE.'/'.$uuid.'/cancel')->assertNotFound();
        $this->authenticate($this->actor, ['mobile:session', 'devices:read']);
        $this->getJson(self::BASE.'/'.$uuid)->assertForbidden();
        $this->authenticate(User::factory()->create(['role' => 'admin']));
        $this->getJson(self::BASE.'/'.$uuid)->assertForbidden();
        $this->assertSame('prepared', DeviceOnboarding::sole()->status);
    }

    public function test_session_changes_are_allowed_only_before_configuration_and_extra_action_data_is_rejected(): void
    {
        $uuid = $this->prepare();
        $this->patchJson(self::BASE.'/'.$uuid, ['name' => 'Vitrina', 'play_mode' => 0, 'sync_class' => 0])
            ->assertOk()->assertJsonPath('data.name', 'Vitrina');
        $this->postJson(self::BASE.'/'.$uuid.'/events', ['event' => 'local_connected', 'password' => 'no'])
            ->assertUnprocessable();
        $this->postJson(self::BASE.'/'.$uuid.'/events', ['event' => 'local_connected'])
            ->assertOk()->assertJsonPath('data.status', 'local_connected');
        $this->report($uuid);
        $this->patchJson(self::BASE.'/'.$uuid, ['sync_class' => 5])->assertConflict();
        $this->postJson(self::BASE.'/'.$uuid.'/complete', ['online' => true])->assertUnprocessable();
        $this->assertSame(0, Device::count());
    }

    public function test_only_fresh_new_heartbeat_and_expected_sync_complete_and_assignment_is_idempotent(): void
    {
        $device = Device::factory()->create(['mac_address' => 'AA:BB:CC:DD:EE:FF', 'name' => 'Original', 'establishment_id' => null]);
        $this->heartbeat(10);
        $uuid = $this->prepare();
        $this->report($uuid);
        $this->postJson(self::BASE.'/'.$uuid.'/complete')->assertConflict()->assertJsonPath('error.code', 'device_not_verified');
        $this->assertSame('Original', $device->fresh()->name);
        $this->travel(2)->seconds();
        $this->heartbeat(11);
        $this->getJson(self::BASE.'/'.$uuid)->assertOk()->assertJsonPath('data.status', 'ready_to_complete');
        $this->postJson(self::BASE.'/'.$uuid.'/complete')->assertOk()->assertJsonPath('data.status', 'completed');
        $this->postJson(self::BASE.'/'.$uuid.'/complete')->assertOk()->assertJsonPath('data.status', 'completed');
        $this->assertSame('Entrada principal', $device->fresh()->name);
        $this->assertSame($this->establishment->id, $device->fresh()->establishment_id);
        $this->assertSame(1, Device::count());
        $this->assertSame(1, AuditLog::where('action', 'mobile_onboarding_completed')->count());
        $this->postJson(self::BASE.'/'.$uuid.'/cancel')->assertConflict();
        $this->assertSame('completed', DeviceOnboarding::sole()->status);
    }

    public function test_fresh_signal_without_increment_wrong_identity_stale_time_or_wrong_sync_never_completes(): void
    {
        $this->heartbeat(5);
        $uuid = $this->prepare();
        $this->report($uuid);
        $this->travel(2)->seconds();
        foreach ([
            ['heartbeatCount' => 5], ['deviceId' => '001122334455'], ['deviceClass' => 5], ['playMode' => 0],
            ['lastSeenIso' => now()->subMinutes(10)->toIso8601String()], ['online' => false],
            ['lastSeenIso' => now()->addHour()->toIso8601String()], ['heartbeatCount' => null],
        ] as $overrides) {
            $this->heartbeat(6, $overrides);
            $this->postJson(self::BASE.'/'.$uuid.'/complete')->assertConflict()->assertJsonPath('error.code', 'device_not_verified');
        }
        $this->assertSame(0, Device::count());
        $this->assertSame('awaiting_online', DeviceOnboarding::sole()->status);
    }

    public function test_duplicate_configuration_event_does_not_move_the_baseline(): void
    {
        $this->heartbeat(4);
        $uuid = $this->prepare();
        $this->report($uuid);
        $baseline = DeviceOnboarding::sole()->verification_baseline_at;
        $this->travel(2)->seconds();
        $this->heartbeat(5);
        $this->postJson(self::BASE.'/'.$uuid.'/events', ['event' => 'configuration_sent'])
            ->assertOk()->assertJsonPath('data.status', 'ready_to_complete');
        $this->assertSame(4, DeviceOnboarding::sole()->baseline_heartbeat_count);
        $this->assertEquals($baseline, DeviceOnboarding::sole()->verification_baseline_at);
    }

    public function test_cloud_failure_never_completes_or_fabricates_inventory(): void
    {
        $uuid = $this->prepare();
        $this->report($uuid);
        $this->cloudUnavailable = true;
        Cache::flush();
        $this->getJson(self::BASE.'/'.$uuid)->assertStatus(503)->assertJsonPath('error.code', 'cloud_unavailable');
        $this->postJson(self::BASE.'/'.$uuid.'/complete')->assertStatus(503);
        $this->assertSame(0, Device::count());
        $this->assertSame('awaiting_online', DeviceOnboarding::sole()->status);
    }

    public function test_assigned_and_soft_deleted_devices_are_not_reclaimed(): void
    {
        $device = Device::factory()->create(['mac_address' => self::MAC, 'establishment_id' => $this->establishment->id]);
        $this->withHeader('Idempotency-Key', 'operation-assigned')->postJson(self::BASE, $this->payload())
            ->assertConflict()->assertJsonPath('error.code', 'device_already_assigned');
        $device->update(['establishment_id' => null]);
        $device->delete();
        $this->postJson(self::BASE, $this->payload())->assertConflict();
        $this->assertSame(0, DeviceOnboarding::count());
    }

    public function test_expiry_and_cancellation_release_reservation_without_physical_rollback(): void
    {
        $uuid = $this->prepare();
        $this->report($uuid);
        $this->postJson(self::BASE.'/'.$uuid.'/cancel')->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->postJson(self::BASE.'/'.$uuid.'/events', ['event' => 'local_connected'])->assertConflict();
        $this->assertNull(DeviceOnboarding::sole()->active_identifier);
        $next = $this->prepare('operation-next');
        $this->travel(31)->minutes();
        $this->getJson(self::BASE.'/'.$next)->assertOk()->assertJsonPath('data.status', 'expired');
        $this->postJson(self::BASE.'/'.$next.'/complete')->assertConflict();
        $this->postJson(self::BASE.'/'.$next.'/cancel')->assertOk()->assertJsonPath('data.status', 'expired');
        $this->postJson(self::BASE.'/'.$next.'/cancel')->assertOk()->assertJsonPath('data.status', 'expired');
        $this->assertNull(DeviceOnboarding::where('uuid', $next)->sole()->active_identifier);
        $fresh = $this->prepare('operation-after-expiry');
        $this->assertNotSame($next, $fresh);
        $this->assertSame('prepared', DeviceOnboarding::where('uuid', $fresh)->sole()->status);
        $this->assertSame(0, Device::count());
    }

    public function test_cancelling_expired_attempt_without_prior_status_request_releases_reservation(): void
    {
        $uuid = $this->prepare();
        $this->travel(31)->minutes();
        $this->postJson(self::BASE.'/'.$uuid.'/cancel')->assertOk()->assertJsonPath('data.status', 'expired');
        $this->assertNull(DeviceOnboarding::sole()->active_identifier);
        $this->prepare('operation-recovered');
        $this->assertSame(2, DeviceOnboarding::count());
    }

    public function test_completion_rechecks_assignment_conflict_and_rolls_back_when_audit_fails(): void
    {
        $uuid = $this->prepare();
        $this->report($uuid);
        $this->travel(2)->seconds();
        $this->heartbeat();
        $device = Device::factory()->create(['mac_address' => self::MAC, 'establishment_id' => $this->establishment->id]);
        $this->postJson(self::BASE.'/'.$uuid.'/complete')->assertConflict()->assertJsonPath('error.code', 'device_already_assigned');
        $device->update(['establishment_id' => null]);
        $originalName = $device->name;
        $this->mock(AuditLogService::class)->shouldReceive('log')->once()->andThrow(new \RuntimeException('Audit unavailable'));
        foreach ($this->app['router']->getRoutes() as $route) {
            $route->flushController();
        }
        $this->postJson(self::BASE.'/'.$uuid.'/complete')->assertStatus(500);
        $this->assertSame($originalName, $device->fresh()->name);
        $this->assertNull($device->fresh()->establishment_id);
        $this->assertSame('awaiting_online', DeviceOnboarding::sole()->status);
    }

    public function test_deleted_establishment_fails_session_without_profile_and_releases_mac_for_fresh_prepare(): void
    {
        $uuid = $this->prepare();
        $this->deleteJson('/api/mobile/v1/establishments/'.$this->establishment->id)->assertNoContent();
        $this->getJson(self::BASE.'/'.$uuid)->assertOk()
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.last_result_code', 'establishment_unavailable')
            ->assertJsonPath('data.establishment_id', null)
            ->assertJsonPath('data.provisioning_profile', null);
        $this->assertNull(DeviceOnboarding::sole()->active_identifier);
        $this->postJson(self::BASE.'/'.$uuid.'/complete')->assertConflict();
        $this->postJson(self::BASE.'/'.$uuid.'/events', ['event' => 'local_connected'])->assertConflict();
        $this->postJson(self::BASE.'/'.$uuid.'/cancel')->assertOk()->assertJsonPath('data.status', 'failed');
        $this->establishment = Establishment::create([
            'name' => 'Nuevo destino', 'address' => 'Calle 2',
            'business_type_id' => BusinessType::firstOrCreate(['name' => 'Tienda'])->id,
        ]);
        $next = $this->prepare('operation-new-establishment');
        $this->assertNotSame($uuid, $next);
        $this->assertSame(0, Device::count());
    }

    public function test_complete_persists_missing_target_failure_even_without_status_request(): void
    {
        $uuid = $this->prepare();
        $this->report($uuid);
        $this->establishment->delete();
        $this->postJson(self::BASE.'/'.$uuid.'/complete')->assertConflict();
        $session = DeviceOnboarding::sole();
        $this->assertSame('failed', $session->status);
        $this->assertSame('establishment_unavailable', $session->last_result_code);
        $this->assertNull($session->active_identifier);
        $this->assertSame(0, Device::count());
    }
}
