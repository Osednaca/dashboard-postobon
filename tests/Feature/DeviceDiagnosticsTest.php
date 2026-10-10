<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use App\Services\Z2\Z2DeviceService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DeviceDiagnosticsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private TestHandler $diagnostics;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.debug' => false, 'privatecloud.base_url' => 'http://cloud.test']);
        Http::preventStrayRequests();
        $handler = $this->diagnostics = new TestHandler;
        Log::extend('diagnostic-test', fn () => new Logger('diagnostics', [$handler]));
        config(['logging.channels.diagnostics' => ['driver' => 'diagnostic-test']]);
    }

    public static function invalidTelemetry(): array
    {
        return [
            'scalar' => [['result' => 0, 'device' => 'invalid']],
            'number' => [['result' => 0, 'device' => 12]],
            'null' => [['result' => 0, 'device' => null]],
            'missing' => [['result' => 0]],
            'rejected' => [['result' => -1, 'device' => ['volume' => 50]]],
        ];
    }

    #[DataProvider('invalidTelemetry')]
    public function test_invalid_telemetry_keeps_device_detail_available(array $snapshot): void
    {
        Http::fake(['cloud.test/*' => Http::response($snapshot)]);
        $device = Device::factory()->create();

        $this->assertNull(app(Z2DeviceService::class)->getDeviceDetail($device->mac_address));
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('devices.show', $device))->assertOk()->assertViewHas('deviceDetail', null)
            ->assertViewHas('playlistAvailable', false)->assertSee($device->name);
        $this->assertFalse($this->diagnostics->hasErrorRecords());
    }

    public function test_unavailable_upstream_and_nullable_local_fields_render(): void
    {
        Http::fake(['cloud.test/*' => Http::response([], 503)]);
        $device = Device::factory()->create(['last_heartbeat_at' => null, 'rpm' => null]);

        $this->actingAs(User::factory()->admin()->create())->get(route('devices.show', $device))
            ->assertOk()->assertViewHas('deviceVolume', null)->assertSee('Nunca');
    }

    public function test_detail_loads_only_ten_latest_heartbeats(): void
    {
        Http::fake(['cloud.test/*' => Http::response(['result' => 0, 'device' => ['playlist' => []]])]);
        $device = Device::factory()->create();
        foreach (range(1, 25) as $minutes) {
            $device->heartbeats()->create(['rpm' => $minutes, 'received_at' => now()->subMinutes($minutes)]);
        }

        $this->actingAs(User::factory()->admin()->create())->get(route('devices.show', $device))
            ->assertOk()->assertViewHas('device', function (Device $device): bool {
                return $device->relationLoaded('heartbeats') && $device->heartbeats->count() === 10
                    && $device->heartbeats->pluck('rpm')->all() === range(1, 10);
            });
        $this->assertSame(25, $device->heartbeats()->count());
    }

    public function test_unexpected_device_error_reaches_diagnostics_with_safe_reference(): void
    {
        $device = Device::factory()->create();
        $this->mock(Z2DeviceService::class)->shouldReceive('getDeviceDetail')
            ->once()->andThrow(new \TypeError('secret-token-in-error-message'));

        $response = $this->actingAs(User::factory()->admin()->create())
            ->withHeader('Authorization', 'Bearer secret-token-in-header')
            ->get(route('devices.show', $device).'?token=secret-token-in-query');

        $response->assertStatus(500)->assertHeader('X-Error-Reference')
            ->assertDontSee('secret-token')->assertDontSee('TypeError');
        $record = $this->diagnostics->getRecords()[0];
        $this->assertSame('TypeError', $record->context['exception_class']);
        $this->assertSame('devices.show', $record->context['route']);
        $this->assertSame($response->headers->get('X-Error-Reference'), $record->context['reference']);
        $this->assertStringNotContainsString('secret-token', json_encode($record->toArray()));
        $this->assertArrayHasKey('line', $record->context);
        $this->assertArrayHasKey('trace', $record->context);
    }

    public function test_deferred_view_failure_is_also_reported(): void
    {
        Http::fake(['cloud.test/*' => Http::response(['result' => 0, 'device' => []])]);
        $device = Device::factory()->create();
        View::composer('devices.show', function (): void {
            throw new \RuntimeException('secret-token-in-view');
        });

        $this->actingAs(User::factory()->admin()->create())->get(route('devices.show', $device))
            ->assertStatus(500)->assertHeader('X-Error-Reference')->assertDontSee('secret-token');
        $this->assertTrue($this->diagnostics->hasErrorRecords());
    }

    public function test_normal_not_found_and_validation_responses_are_preserved(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/devices/999999')
            ->assertNotFound()->assertHeaderMissing('X-Error-Reference');
        Route::post('/diagnostics-validation-test', fn () => request()->validate(['name' => 'required']));
        $this->postJson('/diagnostics-validation-test', [])->assertUnprocessable()
            ->assertJsonValidationErrors('name')->assertHeaderMissing('X-Error-Reference');
        $this->assertFalse($this->diagnostics->hasErrorRecords());
    }
}
