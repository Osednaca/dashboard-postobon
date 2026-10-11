<?php

namespace Tests\Feature;

use App\Models\BusinessType;
use App\Models\Campaign;
use App\Models\Device;
use App\Models\Establishment;
use App\Models\User;
use App\Models\Wl35DeviceProfile;
use App\Services\DashboardService;
use App\Services\DashboardSnapshotService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DashboardSnapshotTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'privatecloud.base_url' => 'http://cloud.test', 'privatecloud.token' => 'test-token',
            'unifiedfleet.base_url' => 'http://gateway.test', 'unifiedfleet.token' => 'test-token',
            'unifiedfleet.fallback_base_url' => '',
        ]);
        Http::preventStrayRequests();
    }

    private function establishment(array $attributes = []): Establishment
    {
        return Establishment::create(array_merge([
            'business_type_id' => BusinessType::firstOrCreate(['name' => 'Tienda'])->id,
            'name' => 'Tienda Centro', 'address' => 'Calle 1', 'latitude' => 4.6, 'longitude' => -74.1,
            'wifi_ssid' => 'private-network', 'wifi_password' => 'private-secret',
        ], $attributes));
    }

    private function live(string $type, string $id, bool $online = true): array
    {
        return compact('type', 'id', 'online') + ['name' => 'Nombre nube', 'power' => false];
    }

    public function test_mixed_live_inventory_counts_three_online_of_four_without_writing_or_syncing(): void
    {
        $place = $this->establishment();
        $device = Device::factory()->create([
            'mac_address' => 'AA:BB:CC:DD:EE:FF', 'name' => 'Entrada local',
            'status' => 'offline', 'establishment_id' => $place->id,
        ]);
        Device::factory()->create(['mac_address' => 'SECOND', 'establishment_id' => $place->id]);
        Wl35DeviceProfile::create(['device_id' => 'fan-a', 'name' => 'Caja local', 'establishment_id' => $place->id]);
        Wl35DeviceProfile::create(['device_id' => 'fan-b', 'name' => 'Bodega']);
        Campaign::factory()->active()->create();
        Campaign::factory()->scheduled()->create();
        Http::fake(['gateway.test/api/fleet' => Http::response(['devices' => [
            $this->live('z2', 'aabbccddeeff'), $this->live('z2', 'AA-BB-CC-DD-EE-FF'),
            $this->live('z2', 'SECOND'), $this->live('wl35', 'fan-a'), $this->live('wl35', 'fan-b', false),
        ]])]);
        $user = User::factory()->admin()->create();
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $response = $this->actingAs($user)->get(route('dashboard.index'))->assertOk();
        $data = $response->viewData('data');
        $this->assertSame(['total_devices' => 4, 'online_devices' => 3, 'offline_devices' => 1, 'unknown_devices' => 0], $data['kpis']);
        $this->assertCount(1, $data['map_data']);
        $this->assertCount(3, $data['map_data'][0]['devices']);
        $this->assertSame('Entrada local', $data['map_data'][0]['devices'][0]['name']);
        $this->assertSame('Caja local', $data['map_data'][0]['devices'][2]['name']);
        $this->assertSame(route('devices.show', $device), $data['map_data'][0]['devices'][0]['detail_url']);
        $this->assertSame(1, $data['campaign_statuses']['active']);
        $this->assertSame(1, $data['campaign_statuses']['scheduled']);
        $response->assertSee('3 en línea')->assertDontSee('private-network')->assertDontSee('private-secret');
        $this->assertEmpty(array_filter($queries, fn ($sql) => preg_match('/^\s*(insert|update|delete)\b/i', $sql)));
        $this->assertSame('offline', $device->fresh()->status);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->method() === 'GET' && $request->url() === 'http://gateway.test/api/fleet');
    }

    public function test_provider_failure_keeps_local_inventory_and_never_promotes_stale_online_status(): void
    {
        Device::factory()->online()->create(['mac_address' => 'SAVED']);
        Wl35DeviceProfile::create(['device_id' => 'saved-wl35', 'name' => 'Saved WL35']);
        Http::fake(['gateway.test/*' => Http::response([], 503), 'cloud.test/*' => Http::response([], 503)]);

        $response = $this->actingAs(User::factory()->admin()->create())->get(route('dashboard.index'))->assertOk();
        $this->assertSame(['total_devices' => 2, 'online_devices' => 0, 'offline_devices' => 0, 'unknown_devices' => 2], $response->viewData('data')['kpis']);
        $response->assertSee('2 sin confirmar')->assertSee('No se pudo confirmar la conexión');
        Http::assertNotSent(fn ($request) => $request->method() !== 'GET');
    }

    public function test_failed_z2_fleet_source_uses_direct_cloud_without_losing_healthy_wl35(): void
    {
        $place = $this->establishment();
        Device::factory()->create(['mac_address' => 'AA', 'name' => 'Alias', 'establishment_id' => $place->id]);
        Http::fake([
            'gateway.test/api/fleet' => Http::response([
                'devices' => [$this->live('z2', 'AA', false), $this->live('wl35', 'live-only')],
                'sources' => ['private_cloud' => ['ok' => false], 'wl35' => ['ok' => true]],
            ]),
            'cloud.test/api/devices' => Http::response(['result' => 0, 'devices' => [['deviceId' => 'aa', 'online' => true]]]),
        ]);
        $data = app(DashboardSnapshotService::class)->snapshot();
        $this->assertSame(2, $data['kpis']['online_devices']);
        $this->assertSame(2, $data['kpis']['total_devices']);
        $this->assertSame('Alias', $data['map_data'][0]['devices'][0]['name']);
        Http::assertSentCount(2);
    }

    public static function malformedSnapshots(): array
    {
        return [
            'missing devices' => [[], []],
            'scalar devices' => [['devices' => 'invalid'], ['devices' => 'invalid']],
            'bad rows' => [['devices' => [null, 3, ['type' => 'z2', 'id' => []]]], ['devices' => [null, 3]]],
            'explicit failure' => [['success' => false, 'devices' => [['type' => 'z2', 'id' => 'AA', 'online' => true]]], ['result' => 1, 'devices' => [['deviceId' => 'AA', 'online' => true]]]],
            'missing connectivity' => [['devices' => [['type' => 'z2', 'id' => 'AA']]], []],
            'invalid connectivity' => [['devices' => [['type' => 'z2', 'id' => 'AA', 'online' => 'false']]], []],
        ];
    }

    #[DataProvider('malformedSnapshots')]
    public function test_malformed_or_incomplete_telemetry_stays_unknown(array $fleet, array $cloud): void
    {
        Device::factory()->online()->create(['mac_address' => 'AA']);
        Http::fake(['gateway.test/api/fleet' => Http::response($fleet), 'cloud.test/api/devices' => Http::response($cloud)]);
        $this->assertSame(['total_devices' => 1, 'online_devices' => 0, 'offline_devices' => 0, 'unknown_devices' => 1], app(DashboardSnapshotService::class)->snapshot()['kpis']);
    }

    public function test_map_uses_establishments_accepts_zero_and_excludes_invalid_coordinates_and_deleted_devices(): void
    {
        $place = $this->establishment(['name' => '</script><img src=x onerror=alert(1)>', 'latitude' => 0, 'longitude' => 0]);
        $this->establishment(['name' => 'Empty', 'latitude' => 1]);
        $this->establishment(['name' => 'Missing', 'latitude' => null]);
        $this->establishment(['name' => 'Out of range', 'latitude' => 91]);
        Device::factory()->create(['mac_address' => 'AA', 'establishment_id' => $place->id])->delete();
        Http::fake(['gateway.test/api/fleet' => Http::response(['devices' => []]), 'cloud.test/api/devices' => Http::response(['devices' => []])]);
        $response = $this->actingAs(User::factory()->admin()->create())->get(route('dashboard.index'))->assertOk();
        $data = $response->viewData('data');
        $this->assertCount(2, $data['map_data']);
        $this->assertSame(0.0, $data['map_data'][0]['latitude']);
        $this->assertSame(0.0, $data['map_data'][0]['longitude']);
        $this->assertSame([], $data['map_data'][0]['devices']);
        $this->assertSame(2, $data['unmapped_establishments']);
        $this->assertSame(0, $data['kpis']['total_devices']);
        $response->assertDontSee('</script><img src=x onerror=alert(1)>', false);
        foreach (['Horas de Trabajo', 'Tiempo Activo', 'Actividad por Grupo', 'Rendimiento de Campañas', 'Estado de Dispositivos', 'Estado de Campañas', 'Actividad Reciente', 'Agregar Dispositivo', 'Agregar Horario', 'chart.js', 'devicePreviews('] as $hidden) {
            $response->assertDontSee($hidden, false);
        }
        $response->assertSee('id="device-map" class="relative isolate z-0 ', false);
    }

    public function test_dashboard_requires_authentication_without_requesting_telemetry(): void
    {
        $this->get(route('dashboard.index'))->assertRedirect(route('login'));
        Http::assertNothingSent();
    }

    public function test_duplicate_saved_mac_is_counted_once_and_type_remains_part_of_identity(): void
    {
        $place = $this->establishment();
        Device::factory()->create(['mac_address' => 'AA:BB', 'name' => 'First local', 'establishment_id' => $place->id]);
        Device::factory()->create(['mac_address' => 'AABB', 'name' => 'Duplicate local', 'establishment_id' => $place->id]);
        Wl35DeviceProfile::create(['device_id' => 'AABB', 'name' => 'Separate WL35', 'establishment_id' => $place->id]);
        Http::fake(['gateway.test/api/fleet' => Http::response(['devices' => [
            $this->live('z2', 'aa-bb'), $this->live('wl35', 'AABB', false),
        ]])]);
        $data = app(DashboardSnapshotService::class)->snapshot();
        $this->assertSame(['total_devices' => 2, 'online_devices' => 1, 'offline_devices' => 1, 'unknown_devices' => 0], $data['kpis']);
        $this->assertSame(['First local', 'Separate WL35'], array_column($data['map_data'][0]['devices'], 'name'));
        $this->assertSame(2, Device::count());
    }

    public function test_legacy_api_still_uses_its_original_service_contract(): void
    {
        $legacy = ['kpis' => ['total_working_hours' => 12], 'activity_by_group' => [], 'map_data' => []];
        $this->mock(DashboardService::class)->shouldReceive('getDashboardData')->once()->andReturn($legacy);
        $this->actingAs(User::factory()->admin()->create())->getJson('/api/dashboard')->assertOk()->assertExactJson($legacy);
        Http::assertNothingSent();
    }
}
