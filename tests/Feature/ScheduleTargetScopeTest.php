<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Group;
use App\Models\Media;
use App\Models\Schedule;
use App\Models\User;
use App\Models\Wl35DeviceProfile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ScheduleTargetScopeTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->actingAs(User::factory()->superadmin()->create());
    }

    private function payload(array $attributes = []): array
    {
        return array_replace([
            'name' => 'Scoped instruction', 'type' => 'power_off',
            'recurrence_type' => 'once', 'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
        ], $attributes);
    }

    private function schedule(array $attributes = []): Schedule
    {
        return Schedule::create($this->payload($attributes) + ['status' => 'pending']);
    }

    public function test_new_mixed_group_web_schedules_persist_scope_for_every_control(): void
    {
        $group = Group::factory()->create();
        Device::factory()->create(['group_id' => $group->id]);
        Wl35DeviceProfile::create(['device_id' => 'group35', 'name' => 'Group 35', 'group_id' => $group->id]);
        foreach (['power_on', 'power_off', 'format_sd'] as $type) {
            $this->post('/schedules', $this->payload([
                'name' => $type, 'type' => $type, 'target_scope' => 'group', 'group_id' => $group->id,
            ]))->assertRedirect()->assertSessionHasNoErrors();
            $schedule = Schedule::where('name', $type)->firstOrFail();
            $this->assertSame('group', $schedule->target_scope);
            $this->assertSame('group', $schedule->controlTargetScope());
            $this->assertEquals($group->id, $schedule->group_id);
            $this->assertNull($schedule->device_id);
        }
        Http::assertNothingSent();
    }

    public function test_api_infers_concrete_targets_but_requires_explicit_global_controls(): void
    {
        $device = Device::factory()->create();
        $group = Group::factory()->create();
        foreach (['device' => $device->id, 'group' => $group->id] as $scope => $id) {
            $this->postJson('/api/schedules', $this->payload([$scope.'_id' => $id]))
                ->assertCreated()->assertJsonPath('target_scope', $scope);
        }
        $this->postJson('/api/schedules', $this->payload())->assertUnprocessable()
            ->assertJsonValidationErrors('target_scope');
        $this->postJson('/api/schedules', $this->payload(['target_scope' => 'all']))
            ->assertCreated()->assertJsonPath('target_scope', 'all');
        Http::assertNothingSent();
    }

    public function test_conflicting_scope_and_ids_are_rejected_on_create_and_update_without_discarding_them(): void
    {
        $group = Group::factory()->create();
        $device = Device::factory()->create();
        $schedule = $this->schedule(['target_scope' => 'group', 'group_id' => $group->id]);
        foreach ([
            ['target_scope' => 'all', 'group_id' => $group->id],
            ['target_scope' => 'all', 'device_id' => $device->id],
            ['target_scope' => 'device', 'device_id' => $device->id, 'group_id' => $group->id],
            ['target_scope' => 'group', 'group_id' => $group->id, 'device_id' => $device->id],
        ] as $targets) {
            $this->postJson('/api/schedules', $this->payload($targets))->assertUnprocessable();
            $this->putJson('/api/schedules/'.$schedule->id, $targets)->assertUnprocessable();
            $this->assertSame('group', $schedule->fresh()->target_scope);
            $this->assertEquals($group->id, $schedule->fresh()->group_id);
        }
        $this->postJson('/api/schedules', $this->payload(['device_id' => $device->id, 'group_id' => $group->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('target_scope');
    }

    public function test_partial_edits_preserve_scope_and_explicit_scope_switches_clear_old_targets(): void
    {
        $group = Group::factory()->create();
        $device = Device::factory()->create();
        $schedule = $this->schedule(['target_scope' => 'group', 'group_id' => $group->id]);
        $this->putJson('/api/schedules/'.$schedule->id, ['name' => 'Renamed'])
            ->assertOk()->assertJsonPath('target_scope', 'group')->assertJsonPath('group_id', $group->id);
        $this->putJson('/api/schedules/'.$schedule->id, ['device_id' => $device->id])
            ->assertUnprocessable()->assertJsonValidationErrors('device_id');
        $this->putJson('/api/schedules/'.$schedule->id, ['target_scope' => 'device', 'device_id' => $device->id])
            ->assertOk()->assertJsonPath('target_scope', 'device')->assertJsonPath('group_id', null);
        $this->putJson('/api/schedules/'.$schedule->id, ['target_scope' => 'all'])
            ->assertOk()->assertJsonPath('target_scope', 'all')->assertJsonPath('device_id', null);
    }

    public function test_ambiguous_legacy_controls_require_confirmation_and_are_not_labelled_all(): void
    {
        $schedule = $this->schedule();
        $this->assertNull($schedule->controlTargetScope());
        $this->get('/schedules/'.$schedule->id)->assertOk()->assertSee('Destino por confirmar');
        $this->get('/schedules')->assertOk()->assertSee('Destino por confirmar');
        $this->get('/schedules/'.$schedule->id.'/edit')->assertOk()->assertSee("scope: ''", false);
        $this->putJson('/api/schedules/'.$schedule->id, ['name' => 'Still ambiguous'])
            ->assertUnprocessable()->assertJsonValidationErrors('target_scope');
        $this->putJson('/api/schedules/'.$schedule->id, ['target_scope' => 'all'])
            ->assertOk()->assertJsonPath('target_scope', 'all');
        $this->assertSame('all', $schedule->fresh()->controlTargetScope());
    }

    public function test_soft_deleted_targets_are_invalid_and_hard_deletion_never_broadens_scope(): void
    {
        foreach (['group' => Group::factory()->create(), 'device' => Device::factory()->create()] as $scope => $target) {
            $schedule = $this->schedule(['target_scope' => $scope, $scope.'_id' => $target->id]);
            $target->delete();
            $this->postJson('/api/schedules', $this->payload(['target_scope' => $scope, $scope.'_id' => $target->id]))
                ->assertUnprocessable()->assertJsonValidationErrors($scope.'_id');
            $this->putJson('/api/schedules/'.$schedule->id, ['name' => 'Unavailable'])
                ->assertUnprocessable()->assertJsonValidationErrors($scope.'_id');
            $target->forceDelete();
            $schedule->refresh();
            $this->assertSame($scope, $schedule->target_scope);
            $this->assertNull($schedule->{$scope.'_id'});
            $this->assertNull($schedule->controlTargetScope());
            $this->assertSame($scope === 'group' ? 'Grupo no disponible' : 'Dispositivo no disponible', $schedule->targetLabel());
        }
    }

    public function test_recurring_edits_and_legacy_content_preserve_their_scope(): void
    {
        $group = Group::factory()->create();
        $response = $this->postJson('/api/schedules', $this->payload([
            'target_scope' => 'group', 'group_id' => $group->id, 'recurrence_type' => 'daily',
            'starts_on' => now()->addDay()->format('Y-m-d'), 'recurrence_time' => '10:00',
        ]))->assertCreated()->assertJsonPath('target_scope', 'group');
        $this->putJson('/api/schedules/'.$response->json('id'), ['name' => 'Recurring renamed'])
            ->assertOk()->assertJsonPath('target_scope', 'group')->assertJsonPath('recurrence_type', 'daily');
        $legacy = $this->schedule(['type' => 'change_content', 'content_id' => Media::factory()->create()->id]);
        $this->assertSame('Todos', $legacy->targetLabel());
        $this->putJson('/api/schedules/'.$legacy->id, ['name' => 'Content renamed'])
            ->assertOk()->assertJsonPath('target_scope', 'all');
    }

    public function test_migration_backfills_only_unambiguous_concrete_targets(): void
    {
        $group = Group::factory()->create();
        $device = Device::factory()->create();
        $groupSchedule = $this->schedule(['group_id' => $group->id]);
        $deviceSchedule = $this->schedule(['device_id' => $device->id]);
        $ambiguous = $this->schedule();
        $conflicting = $this->schedule(['group_id' => $group->id, 'device_id' => $device->id]);
        $migration = require database_path('migrations/2026_10_17_000002_add_target_scope_to_schedules_table.php');
        $migration->down();
        $migration->up();
        $this->assertSame('group', $groupSchedule->fresh()->target_scope);
        $this->assertSame('device', $deviceSchedule->fresh()->target_scope);
        $this->assertNull($ambiguous->fresh()->target_scope);
        $this->assertNull($conflicting->fresh()->target_scope);
    }

    public function test_legacy_concrete_edits_persist_scope_and_inconsistent_rows_require_explicit_repair(): void
    {
        $group = Group::factory()->create();
        $legacy = $this->schedule(['group_id' => $group->id]);
        $this->putJson('/api/schedules/'.$legacy->id, ['name' => 'Legacy renamed'])
            ->assertOk()->assertJsonPath('target_scope', 'group')->assertJsonPath('group_id', $group->id);
        $inconsistent = $this->schedule(['target_scope' => 'all', 'group_id' => $group->id]);
        $this->putJson('/api/schedules/'.$inconsistent->id, ['name' => 'Do not broaden'])
            ->assertUnprocessable()->assertJsonValidationErrors('group_id');
        $this->assertEquals($group->id, $inconsistent->fresh()->group_id);
        $this->putJson('/api/schedules/'.$inconsistent->id, ['target_scope' => 'group'])
            ->assertOk()->assertJsonPath('target_scope', 'group')->assertJsonPath('group_id', $group->id);
    }

    public function test_model_rejects_inconsistent_explicit_and_legacy_scopes(): void
    {
        foreach ([
            ['target_scope' => 'all', 'group_id' => 1],
            ['target_scope' => 'group', 'device_id' => 1],
            ['target_scope' => 'device'],
            ['target_scope' => 'unknown'],
            ['device_id' => 1, 'group_id' => 2],
            [],
        ] as $attributes) {
            $this->assertNull((new Schedule($attributes))->controlTargetScope());
        }
    }
}
