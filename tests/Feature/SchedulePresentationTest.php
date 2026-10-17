<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SchedulePresentationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'America/Bogota']);
        Http::preventStrayRequests();
        $this->actingAs(User::factory()->superadmin()->create());
    }

    private function schedule(string $name, string $date, string $status = 'pending'): Schedule
    {
        return Schedule::create([
            'name' => $name, 'type' => 'power_on', 'scheduled_at' => $date,
            'status' => $status, 'recurrence_type' => 'once',
        ]);
    }

    public function test_calendar_uses_local_dates_and_every_month_record_independent_of_list_page(): void
    {
        for ($i = 1; $i <= 16; $i++) {
            $this->schedule('Mañana '.$i, '2026-10-17 08:10:00');
        }
        $this->schedule('Inicio de mes', '2026-10-01 00:00:00');
        $this->schedule('Fin de mes', '2026-10-31 23:59:00');
        $this->schedule('Mes anterior', '2026-09-30 23:59:00');
        $this->schedule('Mes siguiente', '2026-11-01 00:00:00');

        $response = $this->get('/schedules?view=calendar&month=2026-10&page=2')->assertOk();
        $response->assertViewHas('schedules', fn ($list) => $list->count() < 15);
        $response->assertViewHas('calendarSchedules', fn ($events) => $events->flatten()->count() === 18
            && $events->get('2026-10-17')->count() === 16
            && $events->get('2026-10-01')->first()->name === 'Inicio de mes'
            && $events->get('2026-10-31')->first()->name === 'Fin de mes');
        $response->assertSee('08:10 · Mañana 16')->assertSee('Octubre 2026')
            ->assertSee('data-calendar-date="2026-10-31"', false)
            ->assertSee('month=2026-09', false)->assertSee('month=2026-11', false);
        Http::assertNothingSent();
    }

    public function test_status_filter_applies_to_both_views_and_pagination_retains_selection(): void
    {
        $this->schedule('Pendiente', '2026-10-12 09:00:00');
        $this->schedule('Fallida', '2026-10-12 10:00:00', 'failed');
        for ($i = 0; $i < 16; $i++) {
            $this->schedule('Ejecutada '.$i, '2026-10-12 11:00:00', 'executed');
        }

        $this->get('/schedules?status=executed&view=calendar&month=2026-10')->assertOk()
            ->assertViewHas('schedules', fn ($list) => $list->total() === 16
                && str_contains($list->nextPageUrl(), 'status=executed')
                && str_contains($list->nextPageUrl(), 'view=calendar')
                && str_contains($list->nextPageUrl(), 'month=2026-10'))
            ->assertViewHas('calendarSchedules', fn ($events) => $events->flatten()->count() === 16
                && $events->flatten()->every(fn ($schedule) => $schedule->status === 'executed'));
    }

    public function test_refresh_get_reflects_status_changes_and_preserves_view_month_and_filter(): void
    {
        $schedule = $this->schedule('Apagado', '2026-10-12 09:00:00');
        $url = '/schedules?status=pending&view=calendar&month=2026-10';
        $this->get($url)->assertOk()->assertViewHas('calendarSchedules', fn ($events) => $events->flatten()->count() === 1);
        $schedule->update(['status' => 'executed']);
        $this->get($url)->assertOk()->assertViewHas('calendarSchedules', fn ($events) => $events->isEmpty())
            ->assertViewHas('view', 'calendar')->assertViewHas('status', 'pending')
            ->assertSee('No hay programaciones con este estado en este mes.');
    }

    public function test_empty_february_has_real_days_and_invalid_filters_are_rejected(): void
    {
        $this->get('/schedules?view=calendar&month=2027-02')->assertOk()
            ->assertSee('Febrero 2027')->assertSee('data-calendar-date="2027-02-28"', false)
            ->assertDontSee('data-calendar-date="2027-02-29"', false)
            ->assertSee('No hay programaciones en este mes.');
        $this->getJson('/schedules?status=bogus&month=2026-99&view=bogus')
            ->assertUnprocessable()->assertJsonValidationErrors(['status', 'month', 'view']);
        $this->getJson('/schedules?month=0000-01')->assertUnprocessable()->assertJsonValidationErrors('month');
        $this->get('/schedules?month=9999-12')->assertOk();
    }

    public function test_web_submission_rejects_retired_action_and_allows_supported_action(): void
    {
        $this->get('/schedules/create')->assertOk()
            ->assertSee('El contenido se enviará a todos los dispositivos registrados de los modelos 42 y 35.');
        $payload = ['name' => 'Prueba', 'type' => 'activate_campaign', 'target_scope' => 'all',
            'recurrence_type' => 'once', 'scheduled_at' => '2026-10-18T08:30'];
        $this->post('/schedules', $payload)->assertSessionHasErrors('type');
        $payload['type'] = 'power_on';
        $this->post('/schedules', $payload)->assertRedirect('/schedules')->assertSessionHasNoErrors();
        $schedule = Schedule::sole();
        $this->put('/schedules/'.$schedule->id, array_replace($payload, ['type' => 'activate_campaign']))
            ->assertSessionHasErrors('type');
        $this->assertSame('power_on', $schedule->fresh()->type);
        Http::assertNothingSent();
    }

    public function test_cancelled_filter_and_legacy_campaign_conversion(): void
    {
        $schedule = $this->schedule('Cancelada', '2026-10-12 09:00:00', 'cancelled');
        $this->get('/schedules?status=cancelled&month=2026-10')->assertOk()
            ->assertViewHas('schedules', fn ($list) => $list->total() === 1)
            ->assertSee('Cancelada');
        $campaign = Campaign::factory()->create();
        $schedule->update(['type' => 'activate_campaign', 'campaign_id' => $campaign->id]);
        $this->putJson('/api/schedules/'.$schedule->id, ['type' => 'power_on'])
            ->assertOk()->assertJsonPath('type', 'power_on')->assertJsonPath('campaign_id', null);
        $this->assertNull($schedule->fresh()->campaign_id);
    }
}
