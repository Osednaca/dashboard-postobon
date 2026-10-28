<?php

namespace Tests\Feature;

use App\Jobs\DistributeFleetVideoJob;
use App\Jobs\FormatFleetStorageJob;
use App\Models\FleetOperation;
use App\Models\FleetUpload;
use App\Models\Media;
use App\Models\User;
use App\Models\Wl35DeviceMedia;
use App\Services\Campaigns\CampaignLifecycle;
use App\Services\Fleet\DeviceMutationLock;
use App\Services\Fleet\FleetMediaPlaybackService;
use App\Services\Fleet\UnifiedFleetClient;
use App\Services\Fleet\VideoRemovalRejected;
use App\Services\Z2\Z2DeviceService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FleetDeviceMutationLockTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
        Http::preventStrayRequests();
        Storage::fake('local');
        $this->actingAs(User::factory()->superadmin()->create());
    }

    public function test_all_targets_are_locked_before_work_and_partial_acquisition_is_released(): void
    {
        $targets = ['wl35:fan-a', 'z2:AABBCCDDEEFF'];
        foreach ($targets as $target) {
            $this->assertSame(CampaignLifecycle::lockName($target), DeviceMutationLock::name($target));
        }
        $this->assertSame(DeviceMutationLock::name($targets[1]), DeviceMutationLock::name('z2:aa:bb:cc:dd:ee:ff'));
        $held = Cache::lock(DeviceMutationLock::name('wl35:fan-a'), 2000);
        $this->assertTrue($held->get());
        try {
            app(DeviceMutationLock::class)->run($targets, fn () => $this->fail('A busy device must prevent every command.'));
            $this->fail('Expected a busy-device rejection.');
        } catch (VideoRemovalRejected) {
            $other = Cache::lock(DeviceMutationLock::name('z2:AABBCCDDEEFF'), 2000);
            $this->assertTrue($other->get());
            $other->release();
        } finally {
            $held->release();
        }
        $this->assertSame('done', app(DeviceMutationLock::class)->run($targets, fn () => 'done'));
    }

    public function test_manual_deletion_does_not_send_or_shift_indices_while_campaign_holds_device(): void
    {
        $mapping = Wl35DeviceMedia::create(['device_id' => 'fan-a', 'video_index' => 2, 'filename' => 'keep.mp4']);
        $held = Cache::lock(CampaignLifecycle::lockName('wl35:fan-a'), 2000);
        $held->get();
        try {
            $this->delete(route('devices.wl35.videos.destroy', 'fan-a'), ['index' => 1])
                ->assertRedirect()->assertSessionHas('error');
            $this->assertSame(2, $mapping->fresh()->video_index);
            Http::assertNothingSent();
        } finally {
            $held->release();
        }
    }

    public function test_distribution_does_not_upload_when_campaign_owns_any_selected_target(): void
    {
        $upload = FleetUpload::create(['user_id' => auth()->id(), 'targets' => ['wl35:fan-a'],
            'original_name' => 'fixture.mp4', 'file_path' => 'fixture.mp4', 'status' => 'queued', 'phase' => 'queued', 'progress' => 0]);
        $gateway = $this->mock(UnifiedFleetClient::class);
        $gateway->shouldNotReceive('uploadPathAndDistribute');
        $held = Cache::lock(CampaignLifecycle::lockName('wl35:fan-a'), 2000);
        $held->get();
        try {
            (new DistributeFleetVideoJob($upload->id))->handle($gateway);
            $this->fail('Expected a busy-device rejection.');
        } catch (VideoRemovalRejected) {
            $this->assertSame('failed', $upload->fresh()->status);
            $this->assertSame(0, Wl35DeviceMedia::count());
        } finally {
            $held->release();
        }
    }

    public function test_ambiguous_manual_deletion_invalidates_identity_without_shifting_another_file(): void
    {
        config(['unifiedfleet.base_url' => 'http://gateway.test', 'unifiedfleet.fallback_base_url' => '', 'unifiedfleet.token' => 'test']);
        $media = Media::factory()->video()->create();
        $mapping = Wl35DeviceMedia::create(['device_id' => 'fan-a', 'media_id' => $media->id, 'video_index' => 2, 'filename' => 'keep.mp4']);
        Http::fake([
            'gateway.test/api/health' => Http::response(['ok' => true]),
            'gateway.test/api/fleet' => Http::response(['devices' => [['id' => 'fan-a', 'type' => 'wl35', 'video_count' => 2, 'session_ready' => true]]]),
            'gateway.test/api/devices/fan-a/video/1' => Http::response(['success' => true, 'index' => 1]),
        ]);

        $this->delete(route('devices.wl35.videos.destroy', 'fan-a'), ['index' => 1])
            ->assertRedirect()->assertSessionHas('error');
        $this->assertSame(2, $mapping->fresh()->video_index);
        $this->assertNull($mapping->fresh()->media_id);
        $this->assertNull($mapping->fresh()->filename);
        Http::assertSent(fn ($request) => $request->method() === 'DELETE' && $request['expected_video_count'] === 2);
    }

    public function test_format_holds_same_lock_during_command_and_clears_mappings_before_release(): void
    {
        Wl35DeviceMedia::create(['device_id' => 'fan-a', 'video_index' => 1, 'filename' => 'old.mp4']);
        $operation = FleetOperation::create(['user_id' => auth()->id(), 'command' => 'format_sd',
            'payload' => ['command' => 'format_sd', 'targets' => ['wl35:fan-a']], 'status' => 'queued']);
        $gateway = $this->mock(UnifiedFleetClient::class);
        $gateway->shouldReceive('sendCommand')->once()->andReturnUsing(function (): array {
            $this->assertFalse(Cache::lock(CampaignLifecycle::lockName('wl35:fan-a'), 2000)->get());

            return ['results' => [['success' => true, 'type' => 'wl35', 'id' => 'fan-a']]];
        });
        (new FormatFleetStorageJob($operation->id))->handle($gateway);
        $this->assertSame(0, Wl35DeviceMedia::count());
        $released = Cache::lock(CampaignLifecycle::lockName('wl35:fan-a'), 2000);
        $this->assertTrue($released->get());
        $released->release();
    }

    public function test_mapping_failure_after_manual_ack_invalidates_old_identity(): void
    {
        config(['unifiedfleet.base_url' => 'http://gateway.test', 'unifiedfleet.fallback_base_url' => '', 'unifiedfleet.token' => 'test']);
        $media = Media::factory()->video()->create();
        $mapping = Wl35DeviceMedia::create(['device_id' => 'fan-a', 'media_id' => $media->id, 'video_index' => 2, 'filename' => 'keep.mp4']);
        Http::fake([
            'gateway.test/api/health' => Http::response(['ok' => true]),
            'gateway.test/api/fleet' => Http::response(['devices' => [['id' => 'fan-a', 'type' => 'wl35', 'video_count' => 2, 'session_ready' => true]]]),
            'gateway.test/api/devices/fan-a/video/1' => Http::response(['success' => true, 'confirmed' => true, 'index' => 1, 'video_count' => 1]),
        ]);
        $event = 'eloquent.updating: '.Wl35DeviceMedia::class;
        Event::listen($event, function (): void {
            throw new \RuntimeException('Fixture mapping failure');
        });
        try {
            $this->delete(route('devices.wl35.videos.destroy', 'fan-a'), ['index' => 1])
                ->assertRedirect()->assertSessionHas('error');
        } finally {
            Event::forget($event);
        }
        $this->assertNull($mapping->fresh()->media_id);
        $this->assertNull($mapping->fresh()->filename);
        $this->assertSame(2, $mapping->fresh()->video_index);
    }

    public function test_playback_and_legacy_z2_mutations_observe_campaign_lock(): void
    {
        $media = Media::factory()->video()->create();
        $held = Cache::lock(CampaignLifecycle::lockName('z2:AABBCCDDEEFF'), 2000);
        $held->get();
        $operations = [
            fn () => app(FleetMediaPlaybackService::class)->play($media, ['z2:AABBCCDDEEFF'], 'fixture.mp4', 'fixture.mp4'),
            fn () => app(Z2DeviceService::class)->formatSd('AA:BB:CC:DD:EE:FF'),
            fn () => app(Z2DeviceService::class)->removeVideoFromDevice('AA:BB:CC:DD:EE:FF', 'fixture.mp4'),
        ];
        try {
            foreach ($operations as $operation) {
                try {
                    $operation();
                    $this->fail('Expected a busy-device rejection.');
                } catch (VideoRemovalRejected $exception) {
                    $this->assertStringContainsString('otra operación', $exception->getMessage());
                }
            }
            Http::assertNothingSent();
        } finally {
            $held->release();
        }
    }
}
