<?php

namespace Tests\Feature;

use App\Models\BusinessType;
use App\Models\DeviceOnboarding;
use App\Models\Establishment;
use App\Models\User;
use App\Support\MobileApi;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MobileOnboardingCloudTargetTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const BASE = '/api/mobile/v1/device-onboardings';

    private array $body;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mobile_onboarding.enabled' => true, 'mobile_onboarding.public_enrollment_identifier' => false,
            'mobile_onboarding.advertisers_code' => '', 'privatecloud.base_url' => 'http://cloud.test',
            'privatecloud.token' => 'private-rest-secret']);
        Http::fake(fn () => Http::response(['result' => 0, 'devices' => []]));
        $user = User::factory()->tecnico()->create();
        $this->withToken($user->createToken('mobile:test', MobileApi::ABILITIES, now()->addDay())->plainTextToken);
        $establishment = Establishment::create(['name' => 'Tienda', 'address' => 'Calle 1',
            'business_type_id' => BusinessType::firstOrCreate(['name' => 'Tienda'])->id]);
        $this->body = ['device_type' => 'z2', 'expected_identifier' => 'AABBCCDDEEFF', 'name' => 'Entrada',
            'establishment_id' => $establishment->id, 'play_mode' => 1, 'sync_class' => 6];
    }

    public function test_default_preserves_network_cfg_without_host_identifier_or_bind_and_null_reuses_key(): void
    {
        // Legacy global settings may not silently rewrite each fan's current destination.
        config(['mobile_onboarding.requires_bind' => true, 'mobile_onboarding.cloud_domain' => 'other.test:8088',
            'mobile_onboarding.advertisers_code' => 'not-public']);
        $response = $this->withHeader('Idempotency-Key', 'preserve-operation')->postJson(self::BASE, $this->body)
            ->assertCreated()->assertJsonPath('data.cloud_domain', null)
            ->assertJsonPath('data.provisioning_profile.enabled', true)
            ->assertJsonPath('data.provisioning_profile.requires_bind', false)
            ->assertJsonPath('data.provisioning_profile.cloud_domain', null)
            ->assertJsonPath('data.provisioning_profile.advertisers_code', null)->assertDontSee('not-public');
        foreach ([null, ''] as $value) {
            $this->postJson(self::BASE, [...$this->body, 'cloud_domain' => $value])->assertCreated()
                ->assertJsonPath('data.id', $response->json('data.id'));
        }
        $this->assertSame(1, DeviceOnboarding::count());
    }

    public function test_explicit_target_needs_public_enrollment_but_preserve_still_works(): void
    {
        config(['mobile_onboarding.advertisers_code' => 'privileged-secret']);
        $this->withHeader('Idempotency-Key', 'custom-operation')->postJson(self::BASE, [...$this->body, 'cloud_domain' => 'fan.example.test:8088'])
            ->assertUnprocessable()->assertJsonPath('error.code', 'cloud_target_requires_enrollment')
            ->assertJsonStructure(['error' => ['fields' => ['cloud_domain']]])->assertDontSee('privileged-secret');
        $this->assertSame(0, DeviceOnboarding::count());
        $this->postJson(self::BASE, $this->body)->assertCreated();
    }

    public function test_invalid_hosts_ports_urls_and_credentials_are_rejected_before_any_cloud_access(): void
    {
        foreach (['https://fan.test:8088', 'fan.test/path:8088', 'user@fan.test:8088', 'fan.test:0',
            'fan.test:65536', 'fan.test:abc', '256.1.2.3:8088', 'fan..test:8088', '-fan.test:8088',
            'fan_test:8088', 'fán.test:8088', 'fan.test:8088?key=1', 'fan.test:8088&Domain=evil',
            '[::1]:8088', 'fan.test', 'fan.test:8088/'] as $value) {
            $this->withHeader('Idempotency-Key', 'invalid-operation')->postJson(self::BASE, [...$this->body, 'cloud_domain' => $value])
                ->assertUnprocessable()->assertJsonStructure(['error' => ['fields' => ['cloud_domain']]]);
        }
        $this->assertSame(0, DeviceOnboarding::count());
        Http::assertNothingSent();
    }

    public function test_target_and_public_profile_are_frozen_across_config_and_idempotent_recovery(): void
    {
        config(['mobile_onboarding.public_enrollment_identifier' => true, 'mobile_onboarding.advertisers_code' => 'public-enrollment-A']);
        $body = [...$this->body, 'cloud_domain' => 'FAN.Example.Test:08088'];
        $response = $this->withHeader('Idempotency-Key', 'frozen-operation')->postJson(self::BASE, $body)->assertCreated()
            ->assertJsonPath('data.cloud_domain', 'fan.example.test:8088')
            ->assertJsonPath('data.provisioning_profile.requires_bind', true);
        $uuid = $response->json('data.id');
        config(['mobile_onboarding.advertisers_code' => 'public-enrollment-B', 'mobile_onboarding.public_enrollment_identifier' => false]);
        $this->getJson(self::BASE.'/'.$uuid)->assertOk()
            ->assertJsonPath('data.provisioning_profile.advertisers_code', 'public-enrollment-A');
        $this->postJson(self::BASE, [...$body, 'cloud_domain' => 'fan.example.test:8088'])->assertCreated()
            ->assertJsonPath('data.id', $uuid)->assertJsonPath('data.provisioning_profile.advertisers_code', 'public-enrollment-A');
        $this->postJson(self::BASE, [...$body, 'cloud_domain' => 'new.test:8088'])->assertConflict()
            ->assertJsonPath('error.code', 'idempotency_conflict');
        $this->patchJson(self::BASE.'/'.$uuid, ['cloud_domain' => 'new.test:8088'])->assertUnprocessable();
        $this->assertStringNotContainsString('private-rest-secret', DeviceOnboarding::sole()->toJson());
    }

    public function test_ipv4_target_works_and_historical_missing_snapshot_is_not_regenerated(): void
    {
        config(['mobile_onboarding.public_enrollment_identifier' => true, 'mobile_onboarding.advertisers_code' => 'public-installation-id']);
        $response = $this->withHeader('Idempotency-Key', 'ipv4-operation')->postJson(self::BASE, [...$this->body, 'cloud_domain' => '10.2.3.4:65535'])
            ->assertCreated()->assertJsonPath('data.provisioning_profile.cloud_domain', '10.2.3.4:65535');
        $session = DeviceOnboarding::sole();
        $session->update(['provisioning_profile' => null]);
        $this->getJson(self::BASE.'/'.$response->json('data.id'))->assertOk()
            ->assertJsonPath('data.provisioning_profile', null);
    }
}
