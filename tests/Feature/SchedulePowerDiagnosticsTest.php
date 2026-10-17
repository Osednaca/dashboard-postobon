<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Group;
use App\Models\Schedule;
use App\Models\Wl35DeviceProfile;
use App\Services\Fleet\UnifiedFleetClient;
use App\Services\Z2\Z2DeviceService;
use Illuminate\Console\Scheduling\Schedule as ConsoleSchedule;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class SchedulePowerDiagnosticsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private string $logPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('info', config('logging.channels.schedule_power.level'));
        $this->assertSame('php://stderr', config('logging.channels.schedule_power.with.stream'));
        $this->logPath = tempnam(sys_get_temp_dir(), 'power-log-');
        config(['logging.default' => 'null', 'logging.channels.single.level' => 'emergency',
            'logging.channels.schedule_power.with.stream' => $this->logPath]);
        Log::forgetChannel('schedule_power');
    }

    protected function tearDown(): void
    {
        Log::forgetChannel('schedule_power');
        unlink($this->logPath);
        parent::tearDown();
    }

    private function records(): array
    {
        return array_map(fn ($line) => json_decode($line, true, 512, JSON_THROW_ON_ERROR),
            file($this->logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
    }

    private function schedule(array $attributes = []): Schedule
    {
        return Schedule::create(array_replace(['name' => 'Diagnostic', 'type' => 'power_off',
            'scheduled_at' => now()->subMinute(), 'status' => 'pending', 'recurrence_type' => 'once'], $attributes));
    }

    #[DataProvider('powerFlows')]
    public function test_real_logger_records_exact_group_before_delivery_and_correlated_results(string $type, bool $manual): void
    {
        $group = Group::factory()->create();
        Device::factory()->create(['mac_address' => 'AA:BB', 'group_id' => $group->id]);
        Device::factory()->create(['mac_address' => 'EEEE']);
        Wl35DeviceProfile::create(['device_id' => 'selected', 'name' => 'Selected', 'group_id' => $group->id]);
        Wl35DeviceProfile::create(['device_id' => 'outside', 'name' => 'Outside']);
        $schedule = $this->schedule(['type' => $type, 'target_scope' => 'group', 'group_id' => $group->id]);
        $this->mock(Z2DeviceService::class)->shouldReceive($type === 'power_on' ? 'powerOn' : 'powerOff')
            ->once()->with('AA:BB')->andReturnUsing(function () {
                $resolved = $this->records()[1]['context'];
                $this->assertSame('resolved', $resolved['event']);
                $this->assertSame(['z2:AABB', 'wl35:selected'], $resolved['requested_targets']);

                return true;
            });
        $this->mock(UnifiedFleetClient::class)->shouldReceive('sendCommand')->once()
            ->with(['command' => 'power', 'targets' => ['wl35:selected'], 'value' => $type === 'power_on'], false)
            ->andReturn(['results' => [['key' => 'wl35:selected', 'type' => 'wl35', 'id' => 'selected', 'success' => true]]]);
        $this->artisan('schedules:process', $manual ? ['--schedule' => $schedule->id, '--force' => true] : [])->assertSuccessful();

        $records = $this->records();
        $this->assertSame(['started', 'resolved', 'recipient', 'recipient', 'completed'], array_column(array_column($records, 'context'), 'event'));
        $this->assertCount(1, array_unique(array_column(array_column($records, 'context'), 'run_id')));
        foreach ($records as $record) {
            $this->assertSame('SCHEDULE_POWER_DIAGNOSTIC', $record['message']);
            $this->assertSame('INFO', $record['level_name']);
            $this->assertSame('power-scope-v1', $record['context']['revision']);
            $this->assertSame($manual ? 'manual' : 'automatic', $record['context']['mode']);
            $this->assertSame($schedule->id, $record['context']['schedule_id']);
            $this->assertSame($group->id, $record['context']['group_id']);
            $this->assertNull($record['context']['device_id']);
            $this->assertSame('group', $record['context']['stored_scope']);
            $this->assertSame('group', $record['context']['resolved_scope']);
        }
        $this->assertSame(2, $records[1]['context']['target_count']);
        $this->assertSame(['z2:AABB', 'wl35:selected'], array_column(array_slice(array_column($records, 'context'), 2, 2), 'target'));
        $this->assertTrue($records[4]['context']['accepted']);
        Http::assertNothingSent();
    }

    public static function powerFlows(): array
    {
        return [['power_on', true], ['power_off', true], ['power_on', false], ['power_off', false]];
    }

    public function test_rejected_and_exception_runs_have_distinct_ids_and_no_private_error_body(): void
    {
        $this->mock(Z2DeviceService::class)->shouldReceive('powerOff')->once()->with('AABB')
            ->andThrow(new \RuntimeException('secret token provider URL and body'));
        $ambiguous = $this->schedule();
        $this->artisan('schedules:process')->assertSuccessful();
        $records = $this->records();
        $this->assertSame('ambiguous_scope', $records[1]['context']['reason']);
        $this->assertSame([], $records[1]['context']['requested_targets']);
        $this->assertFalse($records[2]['context']['accepted']);
        $this->assertSame('failed', $ambiguous->fresh()->status);

        $device = Device::factory()->create(['mac_address' => 'AABB']);
        $failed = $this->schedule(['target_scope' => 'device', 'device_id' => $device->id]);
        $this->artisan('schedules:process')->assertSuccessful();
        $records = $this->records();
        $this->assertCount(2, array_unique(array_column(array_column($records, 'context'), 'run_id')));
        $this->assertSame('recipient', $records[5]['context']['event']);
        $this->assertFalse($records[5]['context']['accepted']);
        $this->assertSame(\RuntimeException::class, $records[5]['context']['error_class']);
        $this->assertSame('recipient_failure', $records[6]['context']['reason']);
        $this->assertSame('failed', $failed->fresh()->status);
        $this->assertStringNotContainsString('secret token', file_get_contents($this->logPath));
        Http::assertNothingSent();
    }

    public function test_format_is_not_emitted_on_the_power_channel(): void
    {
        $this->schedule(['type' => 'format_sd']);
        $this->artisan('schedules:process')->assertSuccessful();
        $this->assertSame([], $this->records());
        Http::assertNothingSent();
    }

    public function test_unavailable_diagnostic_stream_does_not_change_delivery(): void
    {
        // A regular file cannot be used as a directory for the diagnostic stream.
        config(['logging.channels.schedule_power.with.stream' => $this->logPath.'/missing.log']);
        $device = Device::factory()->create(['mac_address' => 'AABB']);
        $schedule = $this->schedule(['target_scope' => 'device', 'device_id' => $device->id]);
        $this->mock(Z2DeviceService::class)->shouldReceive('powerOff')->once()->with('AABB')->andReturnTrue();
        $this->artisan('schedules:process')->assertSuccessful();
        $this->assertSame('executed', $schedule->fresh()->status);
        $this->assertNull($schedule->fresh()->last_error);
        $this->assertSame([], $this->records());
        Http::assertNothingSent();
    }

    public function test_scheduler_captures_and_forwards_both_child_streams(): void
    {
        $event = collect(app(ConsoleSchedule::class)->events())->first(fn ($event) => str_contains($event->command ?? '', 'schedules:process'));
        $this->assertNotNull($event);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertNotSame($event->getDefaultOutput(), $event->output);
        $event->output = tempnam(sys_get_temp_dir(), 'schedule-output-');
        $event->command = (new Process([PHP_BINARY, '-r', 'fwrite(STDOUT, "SCHEDULE_OUTPUT_TEST_OUT\n"); fwrite(STDERR, "SCHEDULE_OUTPUT_TEST_ERR\n");']))->getCommandLine();
        try {
            $event->run($this->app);
            $this->assertSame(0, $event->exitCode);
            $this->assertSame("SCHEDULE_OUTPUT_TEST_OUT\nSCHEDULE_OUTPUT_TEST_ERR\n", file_get_contents($event->output));
        } finally {
            unlink($event->output);
        }
    }
}
