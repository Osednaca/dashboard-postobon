<?php

namespace Tests\Feature;

use App\Jobs\DistributeFleetVideoJob;
use App\Jobs\FormatFleetStorageJob;
use App\Jobs\PlayFleetMediaJob;
use App\Models\Device;
use App\Models\FleetOperation;
use App\Models\FleetUpload;
use App\Models\Media;
use App\Models\User;
use App\Services\Fleet\FleetMediaPlaybackService;
use App\Services\Fleet\RetiredDeviceJobExclusions;
use App\Services\Fleet\UnifiedFleetClient;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RetiredFleetJobsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->user = User::factory()->superadmin()->create();
    }

    private function upload(array $targets, ?Media $media = null): FleetUpload
    {
        return FleetUpload::create(['user_id' => $this->user->id, 'source_media_id' => $media?->id,
            'original_name' => 'test.mp4', 'file_path' => 'test.mp4', 'targets' => $targets,
            'status' => 'queued', 'phase' => 'queued', 'progress' => 0]);
    }

    private function retireAndRestore(Device $device): void
    {
        app(RetiredDeviceJobExclusions::class)->snapshot($device);
        $device->forceFill(['retired_for_onboarding_at' => now()])->save();
        $device->delete();
        $device->restore();
        $device->forceFill(['retired_for_onboarding_at' => null])->save();
    }

    public function test_snapshot_filters_old_work_after_restoration_but_allows_new_work_and_other_devices(): void
    {
        $device = Device::factory()->create(['mac_address' => '90:97:D5:E3:A2:5C']);
        $old = $this->upload(['z2:9097d5e3a25c', 'wl35:other']);
        $this->retireAndRestore($device);
        $new = $this->upload(['z2:9097D5E3A25C']);
        $service = app(RetiredDeviceJobExclusions::class);
        $this->assertSame([['wl35:other'], ['z2:9097d5e3a25c']], $service->filter('fleet_upload', $old->id, $old->targets));
        $this->assertSame([$new->targets, []], $service->filter('fleet_upload', $new->id, $new->targets));
        $device->forceFill(['retired_for_onboarding_at' => now()])->save();
        $this->assertSame([[], $new->targets], $service->filter('fleet_upload', $new->id, $new->targets));
    }

    public function test_legacy_identifiers_do_not_collapse_and_empty_identifier_has_no_exclusions(): void
    {
        $device = Device::factory()->create(['mac_address' => 'NEW']);
        $old = $this->upload(['z2:NEW', 'z2:NO-NAME']);
        $this->retireAndRestore($device);
        $service = app(RetiredDeviceJobExclusions::class);
        $this->assertSame([['z2:NO-NAME'], ['z2:NEW']], $service->filter('fleet_upload', $old->id, $old->targets));
        $device->mac_address = null;
        $service->snapshot($device);
        $this->assertSame([['z2:NO-NAME'], ['z2:NEW']], $service->filter('fleet_upload', $old->id, $old->targets));
    }

    public function test_playback_and_distribution_omit_retired_target_but_process_other_targets(): void
    {
        $device = Device::factory()->create(['mac_address' => '90:97:D5:E3:A2:5C']);
        $media = Media::create(['name' => 'Media', 'original_name' => 'test.mp4', 'file_path' => 'test.mp4', 'mime_type' => 'video/mp4', 'size' => 10]);
        $play = $this->upload(['z2:9097D5E3A25C', 'wl35:other'], $media);
        $distribution = $this->upload(['z2:9097D5E3A25C', 'wl35:other']);
        $this->retireAndRestore($device);
        $playback = $this->mock(FleetMediaPlaybackService::class);
        $playback->shouldReceive('play')->once()->withArgs(fn ($m, $targets) => $targets === ['wl35:other'])->andReturn(['results' => []]);
        (new PlayFleetMediaJob($play->id))->handle($playback);
        $gateway = $this->mock(UnifiedFleetClient::class);
        $gateway->shouldReceive('uploadPathAndDistribute')->once()->withArgs(fn ($path, $name, $targets) => $targets === ['wl35:other'])->andReturn(['results' => []]);
        (new DistributeFleetVideoJob($distribution->id))->handle($gateway);
        $this->assertSame(['z2:9097D5E3A25C'], $play->fresh()->result['skipped_targets']);
        $this->assertSame(['z2:9097D5E3A25C'], $distribution->fresh()->result['skipped_targets']);
    }

    public function test_empty_after_filter_never_invokes_any_gateway_and_finishes_cleanly(): void
    {
        $device = Device::factory()->create(['mac_address' => '90:97:D5:E3:A2:5C']);
        $play = $this->upload(['z2:9097D5E3A25C']);
        $distribution = $this->upload(['z2:9097D5E3A25C']);
        $operation = FleetOperation::create(['user_id' => $this->user->id, 'command' => 'format_sd',
            'payload' => ['command' => 'format_sd', 'targets' => ['z2:9097D5E3A25C']], 'status' => 'queued']);
        $this->retireAndRestore($device);
        $playback = $this->mock(FleetMediaPlaybackService::class);
        $playback->shouldNotReceive('play');
        $gateway = $this->mock(UnifiedFleetClient::class);
        $gateway->shouldNotReceive('uploadPathAndDistribute');
        $gateway->shouldNotReceive('sendCommand');
        (new PlayFleetMediaJob($play->id))->handle($playback);
        (new DistributeFleetVideoJob($distribution->id))->handle($gateway);
        (new FormatFleetStorageJob($operation->id))->handle($gateway);
        foreach ([$play, $distribution, $operation] as $record) {
            $this->assertSame('completed', $record->fresh()->status);
            $this->assertSame(['z2:9097D5E3A25C'], $record->fresh()->result['skipped_targets']);
        }
    }

    public function test_format_preserves_other_targets_and_original_payload(): void
    {
        $device = Device::factory()->create(['mac_address' => '90:97:D5:E3:A2:5C']);
        $payload = ['command' => 'format_sd', 'targets' => ['z2:9097D5E3A25C', 'wl35:other']];
        $operation = FleetOperation::create(['user_id' => $this->user->id, 'command' => 'format_sd', 'payload' => $payload, 'status' => 'queued']);
        $this->retireAndRestore($device);
        $gateway = $this->mock(UnifiedFleetClient::class);
        $gateway->shouldReceive('sendCommand')->once()->with(['command' => 'format_sd', 'targets' => ['wl35:other']], true)->andReturn(['results' => []]);
        (new FormatFleetStorageJob($operation->id))->handle($gateway);
        $this->assertSame($payload, $operation->fresh()->payload);
        $this->assertSame(['z2:9097D5E3A25C'], $operation->fresh()->result['skipped_targets']);
    }
}
