<?php

namespace Tests\Feature;

use App\Models\BusinessType;
use App\Models\DeviceOnboarding;
use App\Models\Establishment;
use App\Models\User;
use App\Support\MobileApi;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MobileNumericSyncClassTest extends TestCase
{
    use LazilyRefreshDatabase;

    private array $body;

    private array $devices = [];

    private const BASE = '/api/mobile/v1/device-onboardings';

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->startOfSecond());
        config(['mobile_onboarding.enabled' => true, 'privatecloud.base_url' => 'http://cloud.test']);
        Http::fake(fn () => Http::response(['result' => 0, 'devices' => $this->devices]));
        $user = User::factory()->tecnico()->create();
        $this->withToken($user->createToken('mobile:test', MobileApi::ABILITIES, now()->addDay())->plainTextToken);
        $type = BusinessType::firstOrCreate(['name' => 'Tienda']);
        $establishment = Establishment::create(['name' => 'Local', 'address' => 'Calle 1', 'business_type_id' => $type->id]);
        $this->body = ['device_type' => 'z2', 'expected_identifier' => null, 'name' => 'Entrada',
            'establishment_id' => $establishment->id, 'play_mode' => 1, 'sync_class' => 12345678];
    }

    public function test_large_class_persists_and_only_exact_canonical_heartbeat_completes(): void
    {
        $uuid = $this->withHeader('Idempotency-Key', 'large-class-test')->postJson(self::BASE, $this->body)
            ->assertCreated()->assertJsonPath('data.sync_class', 12345678)->json('data.id');
        $this->postJson(self::BASE.'/'.$uuid.'/register', ['expected_identifier' => '9097D5E3A25C'])->assertOk();
        $this->travel(1)->seconds();
        foreach (['1.2345678e7', '12345678.0', '012345678', 12345678.5, 2147483648, '-1'] as $wrong) {
            $this->devices = [['deviceId' => '9097D5E3A25C', 'online' => true, 'heartbeatCount' => 1,
                'lastSeenIso' => now()->toIso8601String(), 'playMode' => 1, 'deviceClass' => $wrong]];
            Cache::flush();
            $this->postJson(self::BASE.'/'.$uuid.'/complete')->assertConflict();
        }
        $this->devices[0]['deviceClass'] = '12345678';
        Cache::flush();
        $this->postJson(self::BASE.'/'.$uuid.'/complete')->assertOk()->assertJsonPath('data.status', 'completed');
        $this->assertSame(12345678, DeviceOnboarding::where('uuid', $uuid)->firstOrFail()->sync_class);
    }

    public function test_bounds_and_individual_zero_are_enforced_and_migration_rollback_preserves_values(): void
    {
        foreach ([-1, 2147483648, 1.5, '1e3', '1.0'] as $class) {
            $this->withHeader('Idempotency-Key', 'invalid-class-test')->postJson(self::BASE, [...$this->body, 'sync_class' => $class])->assertUnprocessable();
        }
        $this->postJson(self::BASE, [...$this->body, 'play_mode' => 0])->assertUnprocessable();
        $this->withHeader('Idempotency-Key', 'max-class-test')->postJson(self::BASE, [...$this->body, 'sync_class' => 2147483647])->assertCreated();
        $migration = require database_path('migrations/2026_10_28_000003_expand_onboarding_sync_class.php');
        try {
            $migration->down();
            $this->fail('Must not truncate stored classes');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('Cannot shrink', $error->getMessage());
        }
        $this->assertSame(2147483647, DeviceOnboarding::sole()->sync_class);
    }
}
