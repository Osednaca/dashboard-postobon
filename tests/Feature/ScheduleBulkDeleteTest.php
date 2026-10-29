<?php

namespace Tests\Feature;

use App\Models\Schedule;
use App\Models\User;
use App\Services\ScheduleService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ScheduleBulkDeleteTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    private function schedule(string $name): Schedule
    {
        return Schedule::create([
            'name' => $name, 'type' => 'power_off', 'scheduled_at' => now()->addHour(),
            'status' => 'pending', 'recurrence_type' => 'once',
        ]);
    }

    public function test_operational_roles_can_delete_only_selected_schedules(): void
    {
        foreach (['superadmin', 'admin', 'campanas'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $first = $this->schedule('Primera');
            $second = $this->schedule('Segunda');
            $other = $this->schedule('Otra');

            $this->get(route('schedules.index'))->assertOk()->assertSee('data-schedule-select', false)
                ->assertSee('Seleccionar todas las programaciones de esta página');
            $this->post(route('schedules.bulk-delete'), ['ids' => [$first->id, $second->id]])
                ->assertRedirect(route('schedules.index'))->assertSessionHas('success');
            $this->assertModelMissing($first);
            $this->assertModelMissing($second);
            $this->assertModelExists($other);
        }
        Http::assertNothingSent();
    }

    public function test_technicians_and_guests_cannot_delete_schedules(): void
    {
        $schedule = $this->schedule('Protegida');
        $this->post(route('schedules.bulk-delete'), ['ids' => [$schedule->id]])->assertRedirect(route('login'));
        $this->actingAs(User::factory()->tecnico()->create())
            ->post(route('schedules.bulk-delete'), ['ids' => [$schedule->id]])->assertForbidden();
        $this->assertModelExists($schedule);
    }

    public function test_invalid_duplicate_or_missing_ids_do_not_delete_any_schedule(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $schedule = $this->schedule('Conservada');
        foreach ([[], [$schedule->id, $schedule->id], [$schedule->id, 99999999]] as $ids) {
            $this->from(route('schedules.index'))->post(route('schedules.bulk-delete'), ['ids' => $ids])
                ->assertSessionHasErrors();
            $this->assertModelExists($schedule);
        }
    }

    public function test_failure_rolls_back_the_complete_batch(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $first = $this->schedule('Primera');
        $second = $this->schedule('Segunda');
        $service = $this->mock(ScheduleService::class);
        $service->shouldReceive('delete')->once()->with($first->id)->andReturnUsing(function ($id) {
            return Schedule::findOrFail($id)->delete();
        });
        $service->shouldReceive('delete')->once()->with($second->id)->andReturnFalse();

        $this->from(route('schedules.index'))->post(route('schedules.bulk-delete'), ['ids' => [$first->id, $second->id]])
            ->assertSessionHas('error');
        $this->assertModelExists($first);
        $this->assertModelExists($second);
    }
}
