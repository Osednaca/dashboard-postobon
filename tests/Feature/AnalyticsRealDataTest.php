<?php

namespace Tests\Feature;

use App\Models\BusinessType;
use App\Models\Campaign;
use App\Models\CampaignStatistic;
use App\Models\Device;
use App\Models\Establishment;
use App\Models\Group;
use App\Models\Location;
use App\Models\User;
use App\Models\Wl35DeviceProfile;
use App\Services\AnalyticsService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AnalyticsRealDataTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->actingAs(User::factory()->superadmin()->create());
    }

    private function device(array $attributes = []): Device
    {
        return Device::factory()->create(array_replace([
            'name' => 'Equipo real', 'city' => null, 'working_hours' => 0,
            'rpm' => null, 'status' => 'offline', 'last_heartbeat_at' => null,
        ], $attributes));
    }

    public function test_empty_pages_show_real_empty_states_and_zero_totals(): void
    {
        foreach (['' => 'Inventario actual', '/devices' => 'No hay dispositivos registrados.',
            '/campaigns' => 'No hay campañas registradas.', '/groups' => 'No hay datos registrados',
            '/cities' => 'No hay datos registrados'] as $path => $message) {
            $this->get('/analytics'.$path)->assertOk()->assertSee($message)
                ->assertDontSee('Verano 2024')->assertDontSee('Fan 3D 001')
                ->assertViewHas('report', fn ($report) => $report['devices'] === []
                    && $report['groups'] === [] && $report['cities'] === [] && $report['campaigns'] === []
                    && $report['kpis']['device_count'] === 0 && $report['kpis']['total_plays'] === 0
                    && $report['kpis']['working_hours'] === 0.0);
        }
        Http::assertNothingSent();
    }

    public function test_mixed_inventory_is_deduplicated_and_groups_are_kept_by_identity_including_empty(): void
    {
        $first = Group::factory()->create(['name' => 'Grupo coincidente']);
        $second = Group::factory()->create(['name' => 'Segundo grupo']);
        $empty = Group::factory()->create(['name' => 'Grupo vacío']);
        $place = Establishment::create([
            'business_type_id' => BusinessType::firstOrCreate(['name' => 'Tienda'])->id,
            'name' => 'Establecimiento real', 'address' => 'Calle sin ciudad deducible',
        ]);
        $this->device(['mac_address' => 'AA:BB:CC:DD:EE:FF', 'name' => 'Primero', 'group_id' => $first->id,
            'establishment_id' => $place->id, 'city' => 'Bogotá', 'working_hours' => 12.5, 'rpm' => 1000, 'status' => 'online']);
        $this->device(['mac_address' => 'aa-bb-cc-dd-ee-ff', 'name' => 'Duplicado físico', 'group_id' => $second->id,
            'city' => 'Duplicada', 'working_hours' => 999, 'rpm' => 999, 'status' => 'offline']);
        $this->device(['mac_address' => 'OTHER', 'name' => 'Segundo', 'group_id' => $second->id,
            'city' => 'Bogotá', 'working_hours' => 3, 'rpm' => 500, 'status' => 'offline']);
        $this->device(['mac_address' => 'UNKNOWN', 'name' => 'Sin ubicación', 'working_hours' => 1, 'status' => 'maintenance']);
        $this->device(['mac_address' => 'DELETED', 'name' => 'Eliminado', 'working_hours' => 999])->delete();
        Wl35DeviceProfile::create(['device_id' => 'fan35', 'name' => 'Caja 35', 'group_id' => $first->id, 'city' => 'Cali']);

        $report = app(AnalyticsService::class)->report();
        $this->assertCount(4, $report['devices']);
        $this->assertSame(16.5, $report['kpis']['working_hours']);
        $this->assertSame(750.0, $report['kpis']['average_rpm']);
        $this->assertSame(1, $report['kpis']['online_count']);
        $this->assertSame(1, $report['kpis']['offline_count']);
        $this->assertSame(2, $report['kpis']['unknown_count']);
        $groups = collect($report['groups'])->keyBy('id');
        $this->assertSame(2, $groups[$first->id]['device_count']);
        $this->assertSame(1, $groups[$second->id]['device_count']);
        $this->assertSame(0, $groups[$empty->id]['device_count']);
        $this->assertSame(1, $groups['']['device_count']);
        $wl35 = collect($report['devices'])->firstWhere('type', '35');
        $this->assertSame('unknown', $wl35['status']);
        $this->assertNull($wl35['working_hours']);
        $this->assertNull($wl35['rpm']);
        $this->assertNull($wl35['total_plays']);
        $this->assertSame('Establecimiento real', collect($report['devices'])->firstWhere('name', 'Primero')['establishment']);
        $this->get('/analytics/devices')->assertOk()->assertSee('Caja 35')->assertSee('No disponible')
            ->assertSee('Sin confirmar')->assertDontSee('Duplicado físico')->assertDontSee('Eliminado');
        $this->get('/analytics/groups')->assertOk()->assertSee('Grupo vacío')->assertSee('Sin grupo');
        Http::assertNothingSent();
    }

    public function test_city_uses_current_field_then_historical_location_and_preserves_unassigned_devices(): void
    {
        $history = Location::factory()->create(['city' => 'Medellín']);
        $this->device(['name' => 'Ciudad actual', 'city' => '  Bogotá  ', 'location_id' => $history->id]);
        $this->device(['name' => 'Historia 42', 'city' => ' ', 'location_id' => $history->id]);
        $this->device(['name' => 'Sin ciudad']);
        Wl35DeviceProfile::create(['device_id' => 'history35', 'name' => 'Historia 35', 'location_id' => $history->id]);
        Wl35DeviceProfile::create(['device_id' => 'current35', 'name' => 'Ciudad 35', 'city' => 'Cali']);

        $report = app(AnalyticsService::class)->report();
        $cities = collect($report['cities'])->keyBy('city');
        $this->assertSame(1, $cities['Bogotá']['device_count']);
        $this->assertSame(2, $cities['Medellín']['device_count']);
        $this->assertSame(1, $cities['Cali']['device_count']);
        $this->assertSame(1, $cities['Sin ciudad']['device_count']);
        $this->assertSame(5, array_sum(array_column($report['cities'], 'device_count')));
        $this->get('/analytics/cities')->assertOk()->assertSee('Bogotá')->assertSee('Medellín')->assertSee('Sin ciudad')
            ->assertDontSee('Mapa de Actividad');
        Http::assertNothingSent();
    }

    public function test_campaigns_and_device_plays_use_stored_statistics_without_merging_duplicate_names(): void
    {
        $device = $this->device(['mac_address' => 'AA:BB:CC', 'name' => 'Principal']);
        $duplicate = $this->device(['mac_address' => 'aa-bb-cc']);
        $first = Campaign::factory()->create(['name' => 'Nombre coincidente', 'status' => 'active']);
        $second = Campaign::factory()->create(['name' => 'Nombre coincidente', 'status' => 'paused']);
        $empty = Campaign::factory()->create(['name' => 'Campaña sin mediciones', 'status' => 'draft']);
        $deleted = Campaign::factory()->create(['name' => 'Campaña eliminada']);
        foreach ([[$first, $device, 100, 7], [$first, $duplicate, 30, 3], [$second, $device, 9, 2], [$deleted, $device, 999, 999]] as [$campaign, $target, $impressions, $plays]) {
            CampaignStatistic::create(['campaign_id' => $campaign->id, 'device_id' => $target->id,
                'impressions' => $impressions, 'plays' => $plays, 'duration' => 14.5, 'date' => '2026-11-13']);
        }
        $deleted->delete();
        $report = app(AnalyticsService::class)->report();
        $campaigns = collect($report['campaigns'])->keyBy('id');
        $this->assertCount(3, $campaigns);
        $this->assertSame(130, $campaigns[$first->id]['total_impressions']);
        $this->assertSame(10, $campaigns[$first->id]['total_plays']);
        $this->assertSame(2, $campaigns[$first->id]['statistics_count']);
        $this->assertSame('paused', $campaigns[$second->id]['status']);
        $this->assertSame(2, $campaigns[$second->id]['total_plays']);
        $this->assertSame(0, $campaigns[$empty->id]['statistics_count']);
        $this->assertSame(0, $campaigns[$empty->id]['total_plays']);
        $this->assertSame(139, $report['kpis']['total_impressions']);
        $this->assertSame(12, $report['kpis']['total_plays']);
        $this->assertSame(12, $report['devices'][0]['total_plays']);
        $this->get('/analytics/campaigns')->assertOk()->assertSee('Campaña sin mediciones')
            ->assertSee('Sin estadísticas registradas')->assertSee('Pausada')->assertDontSee('Campaña eliminada');
        $this->getJson('/api/analytics')->assertOk()->assertJsonPath('total_plays', 12)
            ->assertJsonPath('total_impressions', 139)->assertJsonCount(3, 'activity_by_campaign');
        Http::assertNothingSent();
    }

    public function test_views_escape_actual_names_and_analytics_reads_do_not_mutate_or_contact_hardware(): void
    {
        $this->device(['name' => '<script>unsafe-device</script>', 'city' => '<b>unsafe-city</b>']);
        Group::factory()->create(['name' => '<img src=x onerror=alert(1)>']);
        Campaign::factory()->create(['name' => '<script>unsafe-campaign</script>']);
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });
        foreach (['', '/devices', '/cities', '/groups', '/campaigns'] as $path) {
            $this->get('/analytics'.$path)->assertOk()->assertDontSee('<script>unsafe-', false)
                ->assertDontSee('<b>unsafe-city</b>', false)->assertDontSee('<img src=x', false);
        }
        $this->assertEmpty(array_filter($queries, fn ($sql) => preg_match('/^\s*(insert|update|delete)\b/i', $sql)));
        Http::assertNothingSent();
    }

    public function test_analytics_remains_restricted_to_superadmins(): void
    {
        foreach (['admin', 'campanas', 'tecnico'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            foreach (['', '/devices', '/campaigns', '/groups', '/cities'] as $path) {
                $this->get('/analytics'.$path)->assertForbidden();
                $this->getJson('/api/analytics'.$path)->assertForbidden();
            }
        }
        $this->app['auth']->forgetGuards();
        $this->get('/analytics')->assertRedirect('/login');
        Http::assertNothingSent();
    }

    public function test_query_failure_shows_an_error_instead_of_fabricated_empty_data(): void
    {
        $this->mock(AnalyticsService::class)->shouldReceive('report')->once()->andThrow(new \RuntimeException('Fixture failure'));
        $this->get('/analytics/devices')->assertOk()->assertSee('No se pudieron cargar las analíticas.')
            ->assertDontSee('No hay dispositivos registrados.')->assertViewMissing('report');
        Http::assertNothingSent();
    }
}
