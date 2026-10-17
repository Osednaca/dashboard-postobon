<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Schedule;
use App\Services\ScheduleRecurrenceService;
use App\Services\ScheduleService;
use App\Services\Z2\Z2DeviceService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ScheduleExecutionLifecycleTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'America/Bogota']);
        $this->travelTo(CarbonImmutable::parse('2026-10-17 10:00:00', 'America/Bogota'));
        Http::preventStrayRequests();
    }

    private function schedule(array $attributes = []): Schedule
    {
        return Schedule::create(array_replace([
            'name' => 'Horario de la mañana',
            'type' => 'power_off',
            'device_id' => Device::factory()->online()->create()->id,
            'scheduled_at' => '2026-10-17 09:30:00',
            'recurrence_type' => 'once',
            'status' => 'pending',
        ], $attributes));
    }

    public function test_local_morning_time_roundtrips_through_utc_api_serialization_without_drifting(): void
    {
        $service = app(ScheduleService::class);
        $schedule = $service->create([
            'name' => 'Apagado', 'type' => 'power_off',
            'scheduled_at' => '2026-10-17T10:00', 'recurrence_type' => 'once',
        ]);
        $utc = $schedule->fresh()->toArray()['scheduled_at'];
        $this->assertSame('2026-10-17T15:00:00.000000Z', $utc);

        // The original Eloquent assignment keeps the supplied UTC wall time.
        $unnormalized = new Schedule(['scheduled_at' => $utc]);
        $this->assertSame('2026-10-17 15:00:00', $unnormalized->getAttributes()['scheduled_at']);

        $updated = $service->update($schedule->id, ['scheduled_at' => $utc]);
        $this->assertSame('2026-10-17 10:00:00', $updated->getRawOriginal('scheduled_at'));
        $this->assertSame('10:00', $updated->scheduled_at->format('H:i'));
        $this->assertSame($utc, $updated->toArray()['scheduled_at']);
    }

    public function test_offset_input_and_reschedule_normalize_to_application_time(): void
    {
        $service = app(ScheduleService::class);
        $schedule = $service->create([
            'name' => 'Apagado', 'type' => 'power_off',
            'scheduled_at' => '2026-10-18T10:00:00-05:00',
        ]);
        $this->assertSame('2026-10-18 10:00:00', $schedule->fresh()->getRawOriginal('scheduled_at'));
        $rescheduled = $service->reschedule($schedule->id, '2026-10-19T14:00:00Z');
        $this->assertSame('2026-10-19 09:00:00', $rescheduled->getRawOriginal('scheduled_at'));
    }

    public function test_automatic_execution_preserves_planned_time_and_runs_only_due_pending_schedules_once(): void
    {
        $due = $this->schedule();
        $future = $this->schedule(['scheduled_at' => '2026-10-17 10:01:00']);
        $cancelled = $this->schedule(['status' => 'cancelled']);
        $failed = $this->schedule(['status' => 'failed']);
        $this->mock(Z2DeviceService::class)->shouldReceive('powerOff')
            ->once()->with($due->device->mac_address)->andReturnTrue();

        $this->artisan('schedules:process')->assertSuccessful();
        $this->artisan('schedules:process')->assertSuccessful();

        $due->refresh();
        $this->assertSame('executed', $due->status);
        $this->assertSame('success', $due->last_run_status);
        $this->assertSame('2026-10-17 09:30:00', $due->getRawOriginal('scheduled_at'));
        $this->assertSame('2026-10-17 10:00:00', $due->executed_at->format('Y-m-d H:i:s'));
        $this->assertSame('pending', $future->fresh()->status);
        $this->assertSame('cancelled', $cancelled->fresh()->status);
        $this->assertSame('failed', $failed->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_manual_early_execution_preserves_planned_time_and_does_not_run_other_due_work(): void
    {
        $selected = $this->schedule(['scheduled_at' => '2026-10-18 08:00:00']);
        $other = $this->schedule();
        $this->mock(Z2DeviceService::class)->shouldReceive('powerOff')
            ->once()->with($selected->device->mac_address)->andReturnTrue();

        $result = app(ScheduleService::class)->execute($selected->id);
        $this->assertSame('executed', $result->status);
        $this->assertSame('2026-10-18 08:00:00', $result->getRawOriginal('scheduled_at'));
        $this->assertSame('pending', $other->fresh()->status);
    }

    public function test_one_time_rejection_can_be_retried_without_erasing_planned_time(): void
    {
        $schedule = $this->schedule();
        $this->mock(Z2DeviceService::class)->shouldReceive('powerOff')
            ->twice()->with($schedule->device->mac_address)->andReturn(false, true);
        $service = app(ScheduleService::class);
        $failed = $service->execute($schedule->id);
        $this->assertSame('failed', $failed->status);
        $this->assertSame('failed', $failed->last_run_status);
        $this->assertNotEmpty($failed->last_error);
        $this->assertSame('2026-10-17 09:30:00', $failed->getRawOriginal('scheduled_at'));

        $retried = $service->execute($schedule->id);
        $this->assertSame('executed', $retried->status);
        $this->assertSame('success', $retried->last_run_status);
        $this->assertNull($retried->last_error);
        $this->assertSame('2026-10-17 09:30:00', $retried->getRawOriginal('scheduled_at'));
    }

    public function test_transport_exception_records_failure_and_releases_execution_lock(): void
    {
        $schedule = $this->schedule();
        $this->mock(Z2DeviceService::class)->shouldReceive('powerOff')
            ->once()->andThrow(new \RuntimeException('Fallo de transporte'));
        $this->artisan('schedules:process')->assertSuccessful();
        $this->assertSame('failed', $schedule->fresh()->status);
        $this->assertStringContainsString('no se pudo completar o confirmar la solicitud', $schedule->fresh()->last_error);
        $lock = Cache::lock('schedules:execute:'.$schedule->id, 300);
        $this->assertTrue($lock->get());
        $lock->release();
    }

    public function test_parallel_manual_run_is_skipped_while_the_occurrence_is_locked(): void
    {
        $schedule = $this->schedule();
        $this->mock(Z2DeviceService::class)->shouldReceive('powerOff')->once()
            ->andReturnUsing(function () use ($schedule): bool {
                // Simulate the manual entrypoint arriving during automatic dispatch.
                app(ScheduleService::class)->execute($schedule->id);

                return true;
            });
        $this->artisan('schedules:process')->assertSuccessful();
        $this->assertSame('executed', $schedule->fresh()->status);
    }

    #[DataProvider('recurrences')]
    public function test_recurring_execution_advances_once_in_local_time(
        string $type, array $definition, string $planned, string $next, bool $success,
    ): void {
        $schedule = $this->schedule(array_replace([
            'recurrence_type' => $type, 'recurrence_time' => '09:30', 'scheduled_at' => $planned,
        ], $definition));
        $this->mock(Z2DeviceService::class)->shouldReceive('powerOff')->once()->andReturn($success);
        $this->artisan('schedules:process')->assertSuccessful();
        $this->artisan('schedules:process')->assertSuccessful();
        $schedule->refresh();
        $this->assertSame('pending', $schedule->status);
        $this->assertSame($next, $schedule->getRawOriginal('scheduled_at'));
        $this->assertSame($success ? 'success' : 'failed', $schedule->last_run_status);
        $this->assertSame('2026-10-17 10:00:00', $schedule->executed_at->format('Y-m-d H:i:s'));
    }

    public static function recurrences(): array
    {
        return [
            'daily' => ['daily', [], '2026-10-17 09:30:00', '2026-10-18 09:30:00', true],
            'daily failure' => ['daily', [], '2026-10-17 09:30:00', '2026-10-18 09:30:00', false],
            'weekly' => ['weekly', ['recurrence_days' => [1, 6]], '2026-10-17 09:30:00', '2026-10-19 09:30:00', true],
            'monthly' => ['monthly', ['recurrence_day' => 17], '2026-10-17 09:30:00', '2026-11-17 09:30:00', true],
            'late daily' => ['daily', [], '2026-10-15 09:30:00', '2026-10-18 09:30:00', true],
        ];
    }

    public function test_manual_recurring_execution_consumes_the_selected_future_occurrence(): void
    {
        $schedule = $this->schedule([
            'recurrence_type' => 'daily', 'recurrence_time' => '08:00',
            'scheduled_at' => '2026-10-18 08:00:00',
        ]);
        $this->mock(Z2DeviceService::class)->shouldReceive('powerOff')->once()->andReturnTrue();
        app(ScheduleService::class)->execute($schedule->id);
        $this->assertSame('2026-10-19 08:00:00', $schedule->fresh()->getRawOriginal('scheduled_at'));
    }

    public function test_last_recurrence_finishes_instead_of_recreating_an_expired_occurrence(): void
    {
        $schedule = $this->schedule([
            'recurrence_type' => 'daily', 'recurrence_time' => '09:30',
            'recurrence_ends_at' => '2026-10-17 23:59:59',
        ]);
        $this->mock(Z2DeviceService::class)->shouldReceive('powerOff')->once()->andReturnTrue();
        $this->artisan('schedules:process')->assertSuccessful();
        $this->assertSame('executed', $schedule->fresh()->status);
        $this->assertSame('2026-10-17 09:30:00', $schedule->fresh()->getRawOriginal('scheduled_at'));
    }

    public function test_recurring_definition_honors_future_start_and_month_end(): void
    {
        $prepared = app(ScheduleRecurrenceService::class)->prepare([
            'recurrence_type' => 'monthly', 'recurrence_day' => 31,
            'recurrence_time' => '08:00', 'starts_on' => '2026-11-01',
            'recurrence_ends_at' => '2026-12-01',
        ]);
        $this->assertSame('2026-11-30 08:00:00', $prepared['scheduled_at']->format('Y-m-d H:i:s'));
        $this->assertSame('America/Bogota', $prepared['scheduled_at']->timezoneName);
        $this->assertSame('2026-12-01 23:59:59', $prepared['recurrence_ends_at']->format('Y-m-d H:i:s'));
    }

    public function test_force_requires_a_single_selected_schedule(): void
    {
        $this->mock(Z2DeviceService::class)->shouldNotReceive('powerOff');
        $this->assertSame(1, Artisan::call('schedules:process', ['--force' => true]));
    }
}
