<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Device;
use App\Models\Schedule;
use App\Models\User;
use App\Services\Z2\Z2CampaignSyncService;
use App\Services\Z2\Z2DeviceService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ScheduleRoleAccessTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    private function schedule(array $attributes = []): Schedule
    {
        return Schedule::create(array_replace([
            'name' => 'Apagado de prueba', 'type' => 'power_off',
            'scheduled_at' => now()->addHour(), 'status' => 'pending',
            'recurrence_type' => 'once',
        ], $attributes));
    }

    private function payload(array $attributes = []): array
    {
        return array_replace([
            'name' => 'Programación técnica', 'type' => 'power_off',
            'target_scope' => 'all', 'recurrence_type' => 'once',
            'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
        ], $attributes);
    }

    public function test_technician_can_create_and_edit_device_schedules_on_web_and_api(): void
    {
        $this->actingAs(User::factory()->tecnico()->create());
        $this->post('/schedules', $this->payload())->assertRedirect('/schedules')->assertSessionHasNoErrors();
        $webSchedule = Schedule::sole();
        $this->put('/schedules/'.$webSchedule->id, $this->payload(['name' => 'Cambio técnico']))
            ->assertRedirect('/schedules')->assertSessionHasNoErrors();
        $this->assertSame('Cambio técnico', $webSchedule->fresh()->name);

        $created = $this->postJson('/api/schedules', $this->payload())->assertCreated();
        $this->putJson('/api/schedules/'.$created->json('id'), ['name' => 'Cambio API'])
            ->assertOk()->assertJsonPath('name', 'Cambio API');
    }

    public function test_technician_cannot_create_campaign_schedules_or_convert_device_schedules(): void
    {
        $this->actingAs(User::factory()->tecnico()->create());
        $campaign = Campaign::factory()->create();
        $schedule = $this->schedule();
        foreach (['/schedules', '/api/schedules'] as $url) {
            $this->postJson($url, $this->payload([
                'type' => 'activate_campaign', 'campaign_id' => $campaign->id,
            ]))->assertForbidden();
            $this->putJson($url.'/'.$schedule->id, ['type' => 'activate_campaign', 'campaign_id' => $campaign->id])
                ->assertForbidden();
            $this->postJson($url, $this->payload(['campaign_id' => $campaign->id]))->assertForbidden();
        }
        $this->assertSame(1, Schedule::count());
        $this->assertSame('power_off', $schedule->fresh()->type);
        $this->assertNull($schedule->fresh()->campaign_id);
    }

    public function test_technician_cannot_access_change_delete_or_execute_existing_campaign_schedule(): void
    {
        $this->actingAs(User::factory()->tecnico()->create());
        $schedule = $this->schedule(['type' => 'activate_campaign', 'campaign_id' => Campaign::factory()->create()->id]);
        foreach (['/schedules', '/api/schedules'] as $url) {
            $this->getJson($url.'/'.$schedule->id)->assertForbidden();
            $this->putJson($url.'/'.$schedule->id, ['name' => 'Cambio'])
                ->assertForbidden();
            $this->putJson($url.'/'.$schedule->id, $this->payload(['campaign_id' => null]))
                ->assertForbidden();
            $this->deleteJson($url.'/'.$schedule->id)->assertForbidden();
        }
        $this->get('/schedules/'.$schedule->id.'/edit')->assertForbidden();
        $this->post('/schedules/'.$schedule->id.'/execute')->assertForbidden();
        $this->assertModelExists($schedule);
    }

    public function test_technician_lists_and_forms_exclude_campaign_schedules(): void
    {
        $this->actingAs(User::factory()->tecnico()->create());
        $device = $this->schedule(['name' => 'Programa dispositivo visible']);
        $campaign = $this->schedule(['name' => 'Campaña reservada', 'type' => 'activate_campaign']);
        $this->get('/schedules')->assertOk()->assertSee($device->name)->assertDontSee($campaign->name);
        $this->getJson('/api/schedules')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $device->id);
        $this->get('/schedules/create')->assertOk()->assertDontSee('value="activate_campaign"', false)
            ->assertDontSee('name="campaign_id"', false)->assertSee('value="power_off"', false);
        $this->get('/schedules/'.$device->id.'/edit')->assertOk()->assertDontSee('name="campaign_id"', false);
    }

    public function test_manual_execution_only_runs_the_selected_schedule_and_leaves_pending_campaigns_untouched(): void
    {
        $this->actingAs(User::factory()->tecnico()->create());
        $device = Device::factory()->create(['mac_address' => 'TESTMAC']);
        $selected = $this->schedule(['device_id' => $device->id]);
        $campaign = Campaign::factory()->create(['status' => 'draft']);
        $other = $this->schedule([
            'type' => 'activate_campaign', 'campaign_id' => $campaign->id,
            'scheduled_at' => now()->subHour(),
        ]);
        $this->mock(Z2DeviceService::class)->shouldReceive('powerOff')->once()->with('TESTMAC')->andReturnTrue();
        $this->mock(Z2CampaignSyncService::class)->shouldNotReceive('activate');

        $this->post('/schedules/'.$selected->id.'/execute')->assertRedirect()->assertSessionHas('success');
        $this->assertSame('executed', $selected->fresh()->status);
        $this->assertSame('pending', $other->fresh()->status);
        $this->assertNull($other->fresh()->executed_at);
        $this->assertSame('draft', $campaign->fresh()->status);
    }

    public function test_admin_and_superadmin_retain_campaign_schedules_and_the_scheduled_processor_remains_global(): void
    {
        foreach (['admin', 'superadmin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $campaign = Campaign::factory()->create();
            $response = $this->postJson('/api/schedules', $this->payload([
                'type' => 'activate_campaign', 'campaign_id' => $campaign->id,
            ]))->assertCreated();
            $this->getJson('/api/schedules/'.$response->json('id'))->assertOk();
            $this->get('/schedules/create')->assertOk()->assertSee('value="activate_campaign"', false);
        }
        $device = Device::factory()->create(['mac_address' => 'SCHEDULERMAC']);
        $first = $this->schedule(['device_id' => $device->id, 'scheduled_at' => now()->subMinute()]);
        $second = $this->schedule(['device_id' => $device->id, 'scheduled_at' => now()->subMinute()]);
        $this->mock(Z2DeviceService::class)->shouldReceive('powerOff')->twice()->with('SCHEDULERMAC')->andReturnTrue();
        $this->artisan('schedules:process')->assertSuccessful();
        $this->assertSame('executed', $first->fresh()->status);
        $this->assertSame('executed', $second->fresh()->status);
    }
}
