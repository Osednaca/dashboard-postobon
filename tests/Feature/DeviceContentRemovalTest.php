<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use App\Services\Z2\DeviceContentRemovalTracker;
use App\Services\Z2\Z2DeviceService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DeviceContentRemovalTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const MAC = 'AA:BB:CC:DD:EE:FF';

    protected function setUp(): void
    {
        parent::setUp();
        config(['privatecloud.base_url' => 'http://cloud.test', 'cache.default' => 'array']);
        Http::preventStrayRequests();
    }

    private function snapshot(array $playlist, int $heartbeat = 10, int $seen = 1000): array
    {
        return ['result' => 0, 'device' => [
            'playlist' => $playlist, 'heartbeatCount' => $heartbeat, 'lastSeen' => $seen,
            'online' => true, 'power' => 1, 'volume' => 50,
        ]];
    }

    private function snapshots(array ...$bodies): void
    {
        $sequence = Http::sequence();
        foreach ($bodies as $body) {
            $sequence->push($body);
        }
        Http::fake(['cloud.test/api/devices/AABBCCDDEEFF' => $sequence]);
    }

    public function test_intermediate_empty_snapshot_cannot_allow_reappearing_file_before_expiry(): void
    {
        $order = [];
        $snapshot = $this->snapshot([], 11, 1100);
        Http::fake(function ($request) use (&$order, &$snapshot) {
            $order[] = $request->method();

            return Http::response($request->method() === 'POST'
                ? ['result' => 0, 'removed' => 'old.mp4', 'remainingPlaylist' => []] : $snapshot);
        });

        $this->assertTrue(app(Z2DeviceService::class)->removeVideoFromDevice(self::MAC, 'old.mp4'));
        $state = app(DeviceContentRemovalTracker::class)->state(self::MAC);

        $this->assertSame(['POST', 'GET'], $order);
        $this->assertSame('pending', $state['removals'][0]['status']);
        $snapshot = $this->snapshot(['old.mp4'], 12, 1200);
        $state = app(DeviceContentRemovalTracker::class)->state(self::MAC);
        $this->assertSame([], $state['playlist']);
        $this->assertSame('pending', $state['removals'][0]['status']);
        $this->assertArrayNotHasKey('confirmed', $state);
    }

    public function test_failed_then_empty_reads_do_not_claim_hardware_confirmation(): void
    {
        $this->snapshots(['result' => -1], $this->snapshot([]), $this->snapshot([]), $this->snapshot([], 11, 1100));
        $tracker = app(DeviceContentRemovalTracker::class);
        $tracker->accepted(self::MAC, 'old.mp4');

        $this->assertSame('pending', $tracker->state(self::MAC)['removals'][0]['status']);
        $this->assertSame('pending', $tracker->state(self::MAC)['removals'][0]['status']);
        $this->travel(10)->minutes();
        $this->assertSame([], $tracker->state(self::MAC)['removals']);
    }

    public static function invalidSnapshots(): array
    {
        return [
            'failed response' => [['result' => -1, 'device' => ['playlist' => []]]],
            'missing playlist' => [['result' => 0, 'device' => ['heartbeatCount' => 12, 'lastSeen' => 1200]]],
            'invalid playlist type' => [['result' => 0, 'device' => ['playlist' => '', 'heartbeatCount' => 12, 'lastSeen' => 1200]]],
            'invalid playlist row' => [['result' => 0, 'device' => ['playlist' => [null], 'heartbeatCount' => 12, 'lastSeen' => 1200]]],
        ];
    }

    #[DataProvider('invalidSnapshots')]
    public function test_bad_playlist_never_disposes_a_pending_request(array $bad): void
    {
        $this->snapshots($bad);
        $tracker = app(DeviceContentRemovalTracker::class);
        $tracker->accepted(self::MAC, 'old.mp4');

        $state = $tracker->state(self::MAC);
        $this->assertFalse($state['available']);
        $this->assertSame('pending', $state['removals'][0]['status']);
    }

    public function test_missing_timestamp_keeps_pending_despite_empty_playlist(): void
    {
        $snapshot = $this->snapshot([]);
        unset($snapshot['device']['lastSeen']);
        $this->snapshots($snapshot, $snapshot);
        $tracker = app(DeviceContentRemovalTracker::class);
        $tracker->accepted(self::MAC, 'old.mp4');

        $this->assertSame('pending', $tracker->state(self::MAC)['removals'][0]['status']);
    }

    public function test_expired_request_restores_reported_files_and_notice_has_fixed_retention(): void
    {
        Http::fake(['cloud.test/*' => Http::response($this->snapshot(['old.mp4', 'sd-only.mp4']))]);
        $tracker = app(DeviceContentRemovalTracker::class);
        $tracker->accepted(self::MAC, 'old.mp4');
        $this->assertSame(['sd-only.mp4'], $tracker->state(self::MAC)['playlist']);

        $this->travel(10)->minutes();
        $state = $tracker->state(self::MAC);
        $this->assertSame(['old.mp4', 'sd-only.mp4'], $state['playlist']);
        $this->assertSame('expired', $state['removals'][0]['status']);
        $this->travel(24)->hours();
        $this->assertSame([], $tracker->state(self::MAC)['removals']);
    }

    public function test_expired_request_without_valid_read_warns_instead_of_claiming_deletion(): void
    {
        $this->snapshots(['result' => -1]);
        $tracker = app(DeviceContentRemovalTracker::class);
        $tracker->accepted(self::MAC, 'old.mp4');
        $this->travel(11)->minutes();

        $state = $tracker->state(self::MAC);
        $this->assertFalse($state['available']);
        $this->assertSame('expired', $state['removals'][0]['status']);
    }

    public function test_pending_removals_are_scoped_by_device_and_filename(): void
    {
        Http::fake(['cloud.test/*' => Http::response($this->snapshot(['old.mp4', 'other.mp4']))]);
        $tracker = app(DeviceContentRemovalTracker::class);
        $tracker->accepted(self::MAC, 'old.mp4');

        $this->assertSame(['other.mp4'], $tracker->state('aabbccddeeff')['playlist']);
        $this->assertSame(['old.mp4', 'other.mp4'], $tracker->state('112233445566')['playlist']);
    }

    public function test_format_pending_does_not_hide_files_absent_from_the_acceptance_snapshot(): void
    {
        $this->snapshots($this->snapshot(['old.mp4', 'new.mp4'], 11, 1100), $this->snapshot(['new.mp4'], 12, 1200));
        $tracker = app(DeviceContentRemovalTracker::class);
        $tracker->accepted(self::MAC, null, ['old.mp4']);

        $state = $tracker->state(self::MAC);
        $this->assertSame(['new.mp4'], $state['playlist']);
        $this->assertNull($state['removals'][0]['filename']);
        $this->assertSame('pending', $state['removals'][0]['status']);
        $this->travel(10)->minutes();
        $state = $tracker->state(self::MAC);
        $this->assertSame(['new.mp4'], $state['playlist']);
        $this->assertSame([], $state['removals']);
    }

    public function test_format_captures_only_reported_files_before_sending_command(): void
    {
        $order = [];
        $files = ['old.mp4'];
        Http::fake(function ($request) use (&$order, &$files) {
            $order[] = $request->method();
            if ($request->method() === 'POST') {
                $files = ['old.mp4', 'new.mp4'];

                return Http::response(['result' => 0, 'delivered' => 'queued']);
            }

            return Http::response($this->snapshot($files));
        });

        $this->assertTrue(app(Z2DeviceService::class)->formatSd(self::MAC));
        $state = app(DeviceContentRemovalTracker::class)->state(self::MAC);

        $this->assertSame(['GET', 'POST', 'GET'], $order);
        $this->assertSame(['new.mp4'], $state['playlist']);
    }

    public function test_device_detail_separates_pending_content_and_keeps_sd_only_files(): void
    {
        $device = Device::factory()->create(['mac_address' => self::MAC]);
        Http::fake(['cloud.test/*' => Http::response($this->snapshot(['old.mp4', 'sd-only.mp4']))]);
        app(DeviceContentRemovalTracker::class)->accepted(self::MAC, 'old.mp4');

        $this->actingAs(User::factory()->admin()->create())->get(route('devices.show', $device))
            ->assertOk()->assertViewHas('devicePlaylist', ['sd-only.mp4'])
            ->assertSee('Eliminación solicitada')->assertSee('Pendiente de actualización del dispositivo.')
            ->assertDontSee('El dispositivo confirmó');
    }

    public static function invalidRemovalResponses(): array
    {
        return [
            'request failure' => [['result' => -1], 503],
            'missing acceptance fields' => [['result' => 0], 200],
            'wrong removed filename' => [['result' => 0, 'removed' => 'other.mp4', 'remainingPlaylist' => []], 200],
            'file still in remaining list' => [['result' => 0, 'removed' => 'old.mp4', 'remainingPlaylist' => ['old.mp4']], 200],
            'invalid remaining row' => [['result' => 0, 'removed' => 'old.mp4', 'remainingPlaylist' => [null]], 200],
        ];
    }

    #[DataProvider('invalidRemovalResponses')]
    public function test_failed_or_malformed_remove_response_does_not_hide_the_file(array $body, int $status): void
    {
        Http::fake([
            'cloud.test/api/devices/AABBCCDDEEFF/remove-media' => Http::response($body, $status),
            'cloud.test/api/devices/AABBCCDDEEFF' => Http::response($this->snapshot(['old.mp4'])),
        ]);

        $this->assertFalse(app(Z2DeviceService::class)->removeVideoFromDevice(self::MAC, 'old.mp4'));
        $state = app(DeviceContentRemovalTracker::class)->state(self::MAC);
        $this->assertSame(['old.mp4'], $state['playlist']);
        $this->assertSame([], $state['removals']);
    }

    public function test_remove_and_format_actions_report_acceptance_instead_of_success(): void
    {
        $device = Device::factory()->create(['mac_address' => self::MAC]);
        Http::fake([
            'cloud.test/api/devices/AABBCCDDEEFF/remove-media' => Http::response(['result' => 0, 'removed' => 'old.mp4', 'remainingPlaylist' => []]),
            'cloud.test/api/devices/AABBCCDDEEFF/format-sd' => Http::response(['result' => 0, 'delivered' => 'queued']),
            'cloud.test/api/devices/AABBCCDDEEFF' => Http::response($this->snapshot(['old.mp4'])),
        ]);
        $this->actingAs(User::factory()->admin()->create());

        $this->post(route('devices.remove-media', $device), ['ui_code' => 'old.mp4'])
            ->assertSessionHas('warning')->assertSessionMissing('success');
        $this->post(route('devices.format-sd', $device))->assertSessionHas('warning')->assertSessionMissing('success');
    }

    public function test_bulk_format_reports_accepted_and_failed_counts_without_claiming_completion(): void
    {
        $accepted = Device::factory()->create(['mac_address' => self::MAC]);
        $failed = Device::factory()->create(['mac_address' => '11:22:33:44:55:66']);
        Http::fake([
            'cloud.test/api/devices/AABBCCDDEEFF/format-sd' => Http::response(['result' => 0]),
            'cloud.test/api/devices/112233445566/format-sd' => Http::response(['result' => -1], 503),
            'cloud.test/api/devices/*' => Http::response($this->snapshot(['old.mp4'])),
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('devices.bulk-format-sd'), ['device_ids' => [$accepted->id, $failed->id]])
            ->assertSessionHas('warning', 'Solicitud de formateo aceptada en 1 dispositivos; falló en 1. El borrado físico aún no está verificado.')
            ->assertSessionMissing('success');

        $this->assertSame([], app(DeviceContentRemovalTracker::class)->state($accepted->mac_address)['playlist']);
        $this->assertSame(['old.mp4'], app(DeviceContentRemovalTracker::class)->state($failed->mac_address)['playlist']);
    }
}
