<?php

namespace Tests\Feature;

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

class MobileOnboardingDeferredIdentityTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const BASE = '/api/mobile/v1/device-onboardings';

    private const MAC = '9097D5E3A25C';

    private User $actor;

    private array $body;

    private array $devices = [];

    private bool $cloudUnavailable = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->startOfSecond());
        config(['privatecloud.base_url' => 'http://private-cloud.test', 'mobile_onboarding.enabled' => true]);
        Http::fake(fn () => Http::response($this->cloudUnavailable ? [] : ['result' => 0, 'devices' => $this->devices], $this->cloudUnavailable ? 503 : 200));
        $this->actor = User::factory()->tecnico()->create();
        $this->authenticate($this->actor);
        $establishment = Establishment::create([
            'name' => 'Tienda', 'address' => 'Calle 1',
            'business_type_id' => BusinessType::firstOrCreate(['name' => 'Tienda'])->id,
        ]);
        $this->body = ['device_type' => 'z2', 'name' => 'Entrada', 'establishment_id' => $establishment->id,
            'play_mode' => 1, 'sync_class' => 6, 'expected_identifier' => null];
    }

    private function authenticate(User $user, ?array $abilities = null): void
    {
        $this->app['auth']->forgetGuards();
        $this->withToken($user->createToken('mobile:test', $abilities ?? MobileApi::ABILITIES, now()->addDay())->plainTextToken);
    }

    private function prepare(string $key = 'deferred-0001'): string
    {
        return $this->withHeader('Idempotency-Key', $key)->postJson(self::BASE, $this->body)
            ->assertCreated()->assertJsonPath('data.status', 'awaiting_identity')
            ->assertJsonPath('data.expected_identifier', null)->json('data.id');
    }

    private function register(string $uuid, string $mac = self::MAC)
    {
        return $this->postJson(self::BASE.'/'.$uuid.'/register', ['expected_identifier' => $mac]);
    }

    private function onboarding(string $uuid): DeviceOnboarding
    {
        return DeviceOnboarding::where('uuid', $uuid)->firstOrFail();
    }

    private function heartbeat(int $count, array $overrides = []): void
    {
        $this->devices = [array_replace(['deviceId' => self::MAC, 'online' => true, 'heartbeatCount' => $count,
            'lastSeenIso' => now()->toIso8601String(), 'playMode' => 1, 'deviceClass' => 6], $overrides)];
        Cache::flush();
    }

    public function test_preparation_is_offline_from_cloud_and_null_omission_replay_preserves_original_hash_after_registration(): void
    {
        $uuid = $this->prepare();
        $before = $this->onboarding($uuid);
        $this->assertNull($before->active_identifier);
        $this->getJson(self::BASE.'/'.$uuid)->assertOk()->assertJsonPath('data.provisioning_profile.tcp_port', 9910);
        unset($this->body['expected_identifier']);
        $this->postJson(self::BASE, $this->body)->assertCreated()->assertJsonPath('data.id', $uuid);
        Http::assertNothingSent();
        $this->register($uuid)->assertOk()->assertJsonPath('data.status', 'awaiting_online');
        $this->postJson(self::BASE, $this->body)->assertCreated()->assertJsonPath('data.id', $uuid)
            ->assertJsonPath('data.expected_identifier', self::MAC);
        $this->assertSame($before->request_hash, $this->onboarding($uuid)->request_hash);
        $this->postJson(self::BASE, [...$this->body, 'expected_identifier' => self::MAC])->assertConflict()
            ->assertJsonPath('error.code', 'idempotency_conflict');
    }

    public function test_profile_and_intent_remain_frozen_and_only_registration_can_bind_identity(): void
    {
        config(['mobile_onboarding.public_enrollment_identifier' => true, 'mobile_onboarding.advertisers_code' => 'public-A']);
        $this->body['cloud_domain'] = 'cloud.example:9000';
        $uuid = $this->prepare();
        config(['mobile_onboarding.advertisers_code' => 'public-B', 'mobile_onboarding.enabled' => false]);
        $this->getJson(self::BASE.'/'.$uuid)->assertOk()->assertJsonPath('data.provisioning_profile.advertisers_code', 'public-A');
        $this->patchJson(self::BASE.'/'.$uuid, ['name' => 'Changed'])->assertConflict()->assertJsonPath('error.code', 'onboarding_locked');
        foreach (['complete', 'resume-verification'] as $action) {
            $this->postJson(self::BASE.'/'.$uuid.'/'.$action)->assertConflict()->assertJsonPath('error.code', 'onboarding_identity_required');
        }
        $this->postJson(self::BASE.'/'.$uuid.'/events', ['event' => 'configuration_sent'])->assertConflict();
        $this->postJson(self::BASE.'/'.$uuid.'/events', ['event' => 'local_connected'])->assertConflict();
        Http::assertNothingSent();
        $this->register($uuid)->assertOk()->assertJsonPath('data.name', 'Entrada')->assertJsonPath('data.provisioning_profile', null);
        $this->assertSame('public-A', $this->onboarding($uuid)->provisioning_profile['advertisers_code']);
    }

    public function test_registration_replays_without_moving_baseline_or_deadline_and_mac_cannot_change(): void
    {
        $uuid = $this->prepare();
        $this->heartbeat(10);
        $this->register($uuid, '90:97:d5:e3:a2:5c')->assertOk()->assertJsonPath('data.expected_identifier', self::MAC);
        $before = $this->onboarding($uuid)->getAttributes();
        $this->travel(10)->seconds();
        $this->heartbeat(20);
        $this->register($uuid)->assertOk();
        $this->assertSame($before, $this->onboarding($uuid)->getAttributes());
        $this->register($uuid, 'AABBCCDDEEFF')->assertConflict()->assertJsonPath('error.code', 'onboarding_identity_conflict');
        Http::assertSentCount(1);
    }

    public function test_only_new_matching_heartbeat_completes_original_assignment_and_replay_of_completion_is_safe(): void
    {
        $uuid = $this->prepare();
        $this->heartbeat(10);
        $this->register($uuid)->assertOk();
        $this->postJson(self::BASE.'/'.$uuid.'/complete')->assertConflict();
        $this->travel(1)->seconds();
        foreach ([['deviceClass' => 4], ['playMode' => 0], ['deviceId' => 'AABBCCDDEEFF'], ['lastSeenIso' => now()->subMinutes(2)->toIso8601String()]] as $bad) {
            $this->heartbeat(11, $bad);
            $this->postJson(self::BASE.'/'.$uuid.'/complete')->assertConflict();
        }
        $this->heartbeat(11);
        $this->postJson(self::BASE.'/'.$uuid.'/complete')->assertOk()->assertJsonPath('data.status', 'completed');
        $this->assertDatabaseHas('devices', ['mac_address' => self::MAC, 'name' => 'Entrada', 'establishment_id' => $this->body['establishment_id']]);
        $before = $this->onboarding($uuid)->getAttributes();
        $requestCount = count(Http::recorded());
        $this->register($uuid)->assertOk()->assertJsonPath('data.status', 'completed');
        $this->assertSame($before, $this->onboarding($uuid)->getAttributes());
        Http::assertSentCount($requestCount);
    }

    public function test_expired_unknown_identity_registers_for_verification_only_and_expired_bound_uses_existing_resume(): void
    {
        $uuid = $this->prepare();
        $before = $this->onboarding($uuid);
        $this->travel(31)->minutes();
        $this->getJson(self::BASE.'/'.$uuid)->assertOk()->assertJsonPath('data.status', 'expired')->assertJsonPath('data.provisioning_profile', null);
        Http::assertNothingSent();
        $this->heartbeat(20);
        $this->register($uuid)->assertOk()->assertJsonPath('data.status', 'awaiting_online')->assertJsonPath('data.provisioning_profile', null);
        $registered = $this->onboarding($uuid);
        $this->assertEquals(now()->addMinutes(30), $registered->expires_at);
        $this->assertSame($before->provisioning_profile, $registered->provisioning_profile);
        $this->assertSame(20, $registered->baseline_heartbeat_count);
        $this->travel(31)->minutes();
        $this->getJson(self::BASE.'/'.$uuid)->assertOk()->assertJsonPath('data.status', 'expired');
        $expired = $this->onboarding($uuid)->getAttributes();
        $this->register($uuid)->assertOk()->assertJsonPath('data.status', 'expired');
        $this->assertSame($expired, $this->onboarding($uuid)->getAttributes());
        $this->postJson(self::BASE.'/'.$uuid.'/resume-verification')->assertOk()->assertJsonPath('data.status', 'awaiting_online');
    }

    public function test_cancelled_and_failed_drafts_never_revive_and_expired_cancellation_is_final(): void
    {
        foreach (['active', 'expired', 'failed'] as $case) {
            $uuid = $this->prepare('cancel-'.$case);
            if ($case === 'expired') {
                $this->travel(31)->minutes();
            } elseif ($case === 'failed') {
                $this->onboarding($uuid)->update(['status' => 'failed']);
            }
            $this->postJson(self::BASE.'/'.$uuid.'/cancel')->assertOk()->assertJsonPath('data.status', $case === 'failed' ? 'failed' : 'cancelled');
            $this->register($uuid)->assertConflict()->assertJsonPath('error.code', 'onboarding_inactive');
        }
        Http::assertNothingSent();
    }

    public function test_assignment_soft_delete_and_competing_reservation_conflicts_preserve_draft(): void
    {
        $uuid = $this->prepare();
        $device = Device::create(['mac_address' => '90:97:D5:E3:A2:5C', 'name' => 'Existing', 'establishment_id' => $this->body['establishment_id']]);
        $this->register($uuid)->assertConflict()->assertJsonPath('error.code', 'device_already_assigned');
        $device->update(['establishment_id' => null]);
        $device->delete();
        $this->register($uuid)->assertConflict()->assertJsonPath('error.code', 'device_already_assigned');
        $device->forceDelete();
        $other = $this->prepare('competing-0001');
        $this->register($other)->assertOk();
        $this->register($uuid)->assertConflict()->assertJsonPath('error.code', 'onboarding_in_progress');
        $this->assertNull($this->onboarding($uuid)->expected_identifier);
        $this->travel(31)->minutes();
        $this->register($uuid)->assertOk();
        $this->assertNull($this->onboarding($other)->active_identifier);
        $this->assertSame('expired', $this->onboarding($other)->status);
    }

    public function test_auth_ability_missing_establishment_and_strict_action_payload(): void
    {
        $uuid = $this->prepare();
        foreach ([[], ['expected_identifier' => null], ['expected_identifier' => 'bad'], ['expected_identifier' => self::MAC, 'online' => true], ['expected_identifier' => self::MAC, 'wifi_password' => 'secret'], ['expected_identifier' => self::MAC, 'name' => 'Changed']] as $payload) {
            $this->postJson(self::BASE.'/'.$uuid.'/register', $payload)->assertUnprocessable();
        }
        $this->authenticate(User::factory()->tecnico()->create());
        $this->register($uuid)->assertNotFound();
        $this->authenticate($this->actor, ['devices:read']);
        $this->register($uuid)->assertForbidden();
        $this->authenticate($this->actor);
        Establishment::findOrFail($this->body['establishment_id'])->delete();
        $this->register($uuid)->assertConflict()->assertJsonPath('error.code', 'establishment_unavailable');
        $this->getJson(self::BASE.'/'.$uuid)->assertOk()->assertJsonPath('data.status', 'failed');
        $this->register($uuid)->assertConflict()->assertJsonPath('error.code', 'onboarding_inactive');
        Http::assertNothingSent();
    }

    public function test_cloud_and_audit_failures_roll_back_registration_and_reservation(): void
    {
        $uuid = $this->prepare();
        $before = $this->onboarding($uuid)->getAttributes();
        $this->cloudUnavailable = true;
        $this->register($uuid)->assertStatus(503)->assertJsonPath('error.code', 'cloud_unavailable');
        $this->assertSame($before, $this->onboarding($uuid)->getAttributes());
        $this->cloudUnavailable = false;
        $this->mock(AuditLogService::class)->shouldReceive('log')->once()->andThrow(new \RuntimeException('intentional audit failure'));
        foreach ($this->app['router']->getRoutes() as $route) {
            $route->flushController();
        }
        $this->register($uuid)->assertStatus(500);
        $this->assertSame($before, $this->onboarding($uuid)->getAttributes());
    }

    public function test_nullable_migration_rollback_preserves_unidentified_recovery_records(): void
    {
        $uuid = $this->prepare();
        $migration = require database_path('migrations/2026_10_28_000001_allow_unidentified_device_onboardings.php');
        try {
            $migration->down();
            $this->fail('Rollback must refuse to discard unidentified sessions.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('unidentified sessions exist', $exception->getMessage());
        }
        $this->assertNull($this->onboarding($uuid)->expected_identifier);
        $this->register($uuid)->assertOk();
        $migration->down();
        $migration->up();
        $this->prepare('after-migration');
        $this->assertSame(self::MAC, $this->onboarding($uuid)->expected_identifier);
    }

    public function test_inconsistent_verifying_state_without_identity_never_queries_cloud(): void
    {
        $uuid = $this->prepare();
        $this->onboarding($uuid)->update(['status' => 'awaiting_online']);
        $this->getJson(self::BASE.'/'.$uuid)->assertOk()->assertJsonPath('data.status', 'awaiting_online');
        $this->postJson(self::BASE.'/'.$uuid.'/complete')->assertConflict()->assertJsonPath('error.code', 'onboarding_identity_required');
        Http::assertNothingSent();
    }
}
