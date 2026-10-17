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

    public function test_technician_cannot_read_or_mutate_any_schedule(): void
    {
        $this->actingAs(User::factory()->tecnico()->create());
        $deviceSchedule = $this->schedule();
        $campaignSchedule = $this->schedule(['type' => 'activate_campaign', 'campaign_id' => Campaign::factory()->create()->id]);
        foreach (['/schedules', '/api/schedules'] as $url) {
            $this->getJson($url)->assertForbidden();
            $this->postJson($url, $this->payload())->assertForbidden();
            foreach ([$deviceSchedule, $campaignSchedule] as $schedule) {
                $this->getJson($url.'/'.$schedule->id)->assertForbidden();
                $this->putJson($url.'/'.$schedule->id, $this->payload())->assertForbidden();
                $this->deleteJson($url.'/'.$schedule->id)->assertForbidden();
            }
        }
        $this->get('/schedules/create')->assertForbidden();
        $this->get('/schedules/'.$deviceSchedule->id.'/edit')->assertForbidden();
        $this->post('/schedules/'.$deviceSchedule->id.'/execute')->assertForbidden();
        $this->assertSame('pending', $deviceSchedule->fresh()->status);
        $this->assertSame('pending', $campaignSchedule->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_manual_execution_only_runs_the_selected_schedule_and_leaves_pending_campaigns_untouched(): void
    {
        $this->actingAs(User::factory()->superadmin()->create());
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

    public function test_operational_roles_can_read_historical_campaign_schedules_but_cannot_submit_the_retired_action(): void
    {
        foreach (['admin', 'superadmin', 'campanas'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $campaign = Campaign::factory()->create();
            $historical = $this->schedule(['type' => 'activate_campaign', 'campaign_id' => $campaign->id]);
            $this->postJson('/api/schedules', $this->payload([
                'type' => 'activate_campaign', 'campaign_id' => $campaign->id,
            ]))->assertUnprocessable()->assertJsonValidationErrors('type')
                ->assertJsonPath('errors.type.0', 'La acción Activar Campaña ya no está disponible. Selecciona otra acción.');
            $this->putJson('/api/schedules/'.$historical->id, ['type' => 'activate_campaign'])
                ->assertUnprocessable()->assertJsonValidationErrors('type');
            $this->getJson('/api/schedules/'.$historical->id)->assertOk();
            $this->get('/schedules/'.$historical->id)->assertOk();
            $this->get('/schedules/create')->assertOk()->assertDontSee('value="activate_campaign"', false);
            $this->get('/schedules/'.$historical->id.'/edit')->assertOk()->assertDontSee('value="activate_campaign"', false);
        }
    }

    public function test_the_scheduled_processor_remains_global(): void
    {
        $device = Device::factory()->create(['mac_address' => 'SCHEDULERMAC']);
        $first = $this->schedule(['device_id' => $device->id, 'scheduled_at' => now()->subMinute()]);
        $second = $this->schedule(['device_id' => $device->id, 'scheduled_at' => now()->subMinute()]);
        $this->mock(Z2DeviceService::class)->shouldReceive('powerOff')->twice()->with('SCHEDULERMAC')->andReturnTrue();
        $this->artisan('schedules:process')->assertSuccessful();
        $this->assertSame('executed', $first->fresh()->status);
        $this->assertSame('executed', $second->fresh()->status);
    }
}
