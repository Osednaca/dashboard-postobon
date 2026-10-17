<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\AuditLogSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuditPresentationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'America/Bogota']);
        Http::preventStrayRequests();
        $this->actingAs(User::factory()->superadmin()->create());
    }

    private function entry(array $attributes = []): AuditLog
    {
        return AuditLog::create(array_replace([
            'user_id' => auth()->id(),
            'action' => 'assign_establishment',
            'entity_type' => 'Device',
            'entity_id' => 743,
            'details' => ['establishment_id' => 91],
            'ip_address' => '203.0.113.27',
            'created_at' => '2026-10-17 10:00:00',
        ], $attributes));
    }

    public function test_real_stored_fields_and_escaped_json_details_are_rendered(): void
    {
        $actor = User::factory()->tecnico()->create(['name' => 'María del establecimiento']);
        $this->entry([
            'user_id' => $actor->id,
            'details' => ['name' => '<script>alert("audit")</script>', 'nested' => ['etiqueta' => 'Bogotá']],
        ])->forceFill(['created_at' => '2026-10-17 10:00:00'])->save();

        $this->get('/audit')->assertOk()
            ->assertSee('María del establecimiento')
            ->assertSee('Asignación de establecimiento')->assertSee('value="assign_establishment"', false)
            ->assertSee('Dispositivo 42')->assertSee('value="Device"', false)->assertSee('#743')
            ->assertSee('203.0.113.27')->assertSee('17/10/2026 10:00:00')
            ->assertSee('Bogotá')->assertSee('&lt;script&gt;', false)
            ->assertDontSee('<script>alert(', false)
            ->assertDontSee('Verano 2024')->assertDontSee('Operador 1')
            ->assertDontSee('Exportar')->assertSee('name="user_id"', false)
            ->assertSee('name="action"', false)->assertSee('name="entity_type"', false)
            ->assertSee('name="date"', false)->assertSee('type="submit"', false)
            ->assertViewHas('logs', fn ($logs) => $logs->total() === 1 && $logs->first()->relationLoaded('user'));
        Http::assertNothingSent();
    }

    public function test_null_and_deleted_users_and_missing_details_are_truthful(): void
    {
        $deleted = User::factory()->create();
        $this->entry(['user_id' => $deleted->id, 'action' => 'deleted_actor']);
        $deleted->delete();
        $this->entry(['user_id' => null, 'action' => 'unattributed', 'details' => null, 'ip_address' => null]);

        $this->get('/audit')->assertOk()->assertSee('Sin usuario disponible')
            ->assertSee('deleted_actor')->assertSee('unattributed')
            ->assertSee('Sin detalles registrados')->assertSee('Sin IP registrada')
            ->assertDontSee($deleted->name)
            ->assertViewHas('logs', fn ($logs) => $logs->total() === 2 && $logs->every(fn ($log) => $log->user === null));
    }

    public function test_unknown_historical_action_and_entity_labels_are_escaped_without_losing_filter_values(): void
    {
        $action = '<b>historical-action</b>';
        $entity = '<i>historical-entity</i>';
        $this->entry(['action' => $action, 'entity_type' => $entity]);

        $this->get('/audit?'.http_build_query(['action' => $action, 'entity_type' => $entity]))
            ->assertOk()->assertSee($action)->assertSee($entity)
            ->assertDontSee($action, false)->assertDontSee($entity, false)
            ->assertViewHas('logs', fn ($logs) => $logs->total() === 1);
    }

    public function test_empty_and_filtered_empty_history_do_not_invent_events(): void
    {
        $this->get('/audit')->assertOk()->assertSee('No hay acciones registradas en la auditoría.')
            ->assertDontSee('Verano 2024')->assertDontSee('192.168.1.10');

        $this->entry();
        $this->get('/audit?action=does_not_exist')->assertOk()
            ->assertSee('No hay registros de auditoría con estos filtros.')
            ->assertViewHas('logs', fn ($logs) => $logs->total() === 0);
        $this->assertSame(1, AuditLog::count());
    }

    public function test_each_filter_and_combined_filters_use_actual_values(): void
    {
        $actor = User::factory()->create(['name' => 'Responsable real']);
        $unused = User::factory()->create(['name' => 'Sin actividad']);
        $matching = $this->entry(['user_id' => $actor->id, 'action' => 'custom_event', 'entity_type' => 'Wl35DeviceProfile']);
        $other = $this->entry(['action' => 'publish', 'entity_type' => 'Campaign']);

        foreach ([['user_id' => $actor->id], ['action' => 'custom_event'], ['entity_type' => 'Wl35DeviceProfile'],
            ['user_id' => $actor->id, 'action' => 'custom_event', 'entity_type' => 'Wl35DeviceProfile']] as $filter) {
            $this->get('/audit?'.http_build_query($filter))->assertOk()
                ->assertViewHas('logs', fn ($logs) => $logs->pluck('id')->all() === [$matching->id])
                ->assertViewHas('actions', fn ($actions) => $actions->all() === ['custom_event', 'publish'])
                ->assertViewHas('entityTypes', fn ($types) => $types->all() === ['Campaign', 'Wl35DeviceProfile'])
                ->assertViewHas('users', fn ($users) => $users->contains('id', $actor->id) && ! $users->contains('id', $unused->id));
        }
        $this->get('/audit?action=publish&entity_type=Campaign')->assertOk()
            ->assertViewHas('logs', fn ($logs) => $logs->pluck('id')->all() === [$other->id]);
    }

    public function test_date_filter_includes_both_local_day_boundaries_only(): void
    {
        $ids = [];
        foreach (['2026-10-16 23:59:59', '2026-10-17 00:00:00', '2026-10-17 23:59:59', '2026-10-18 00:00:00'] as $date) {
            $log = $this->entry();
            $log->forceFill(['created_at' => $date])->save();
            $ids[] = $log->id;
        }

        $this->get('/audit?date=2026-10-17')->assertOk()
            ->assertSee('America/Bogota')->assertSee('17/10/2026 00:00:00')->assertSee('17/10/2026 23:59:59')
            ->assertDontSee('16/10/2026')->assertDontSee('18/10/2026')
            ->assertViewHas('logs', fn ($logs) => $logs->pluck('id')->all() === [$ids[2], $ids[1]]);
    }

    public function test_pagination_preserves_valid_filters_and_stable_timestamp_tie_order(): void
    {
        $ids = [];
        for ($i = 0; $i < 17; $i++) {
            $log = $this->entry();
            $log->forceFill(['created_at' => '2026-10-17 10:00:00'])->save();
            $ids[] = $log->id;
        }
        $filters = ['user_id' => auth()->id(), 'action' => 'assign_establishment', 'entity_type' => 'Device', 'date' => '2026-10-17'];
        $this->get('/audit?'.http_build_query($filters + ['irrelevant' => 'discard']))->assertOk()
            ->assertViewHas('logs', function ($logs) use ($filters, $ids) {
                parse_str(parse_url($logs->nextPageUrl(), PHP_URL_QUERY), $query);

                return $logs->total() === 17 && $logs->pluck('id')->all() === array_slice(array_reverse($ids), 0, 15)
                    && $query == $filters + ['page' => 2];
            });
        $this->get('/audit?'.http_build_query($filters + ['page' => 2]))->assertOk()
            ->assertViewHas('logs', fn ($logs) => $logs->pluck('id')->all() === [$ids[1], $ids[0]]);
    }

    public function test_invalid_filter_types_ids_dates_and_page_are_rejected(): void
    {
        foreach ([['user_id' => 999999], ['user_id' => [1]], ['action' => ['create']], ['entity_type' => ['Device']],
            ['action' => str_repeat('a', 256)], ['entity_type' => str_repeat('b', 256)], ['date' => '2026-02-30'],
            ['date' => '0000-01-01'], ['date' => '2026-10-17T00:00:00'], ['page' => 0], ['page' => 'not-a-page']] as $filter) {
            $this->getJson('/audit?'.http_build_query($filter))->assertUnprocessable()
                ->assertJsonValidationErrors(array_keys($filter));
        }
    }

    public function test_audit_remains_restricted_to_superadmins(): void
    {
        $this->entry(['details' => ['private' => 'restricted evidence']]);
        foreach (['admin', 'campanas', 'tecnico'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get('/audit')->assertForbidden()
                ->assertDontSee('restricted evidence');
        }
        auth()->logout();
        $this->get('/audit')->assertRedirect('/login');
        Http::assertNothingSent();
    }

    public function test_normal_database_seeding_does_not_add_or_remove_audit_events(): void
    {
        $existing = $this->entry(['action' => 'preserved_history']);
        // Exercise the normal seeder's dispatch without unrelated random demo factories.
        foreach (['UserSeeder', 'GroupSeeder', 'DeviceSeeder', 'MediaSeeder', 'CampaignSeeder',
            'ScheduleSeeder', 'NotificationSeeder', 'DeviceHeartbeatSeeder', 'CampaignStatisticSeeder'] as $seeder) {
            $this->partialMock('Database\\Seeders\\'.$seeder)->shouldReceive('run')->once();
        }
        $this->partialMock(AuditLogSeeder::class)->shouldNotReceive('run');
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, AuditLog::count());
        $this->assertSame('preserved_history', $existing->fresh()->action);
        Http::assertNothingSent();
    }
}
