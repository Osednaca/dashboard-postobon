<?php

namespace Tests\Feature;

use App\Models\CampaignBaselineFile;
use App\Models\CampaignDevicePlayback;
use App\Models\Media;
use App\Models\Wl35DeviceMedia;
use App\Services\Campaigns\CampaignDeviceTransport;
use App\Services\Fleet\UnifiedFleetClient;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CampaignBaselineTransportTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->freezeTime();
        config(['privatecloud.base_url' => 'http://cloud.test']);
        Storage::fake('local');
        Storage::disk('local')->put('backup.mp4', 'backup');
    }

    private function baseline(string $target, array $attributes = []): CampaignBaselineFile
    {
        $playback = CampaignDevicePlayback::create(['target_key' => $target]);
        $media = Media::factory()->video()->create();

        return CampaignBaselineFile::create(array_merge([
            'playback_id' => $playback->id, 'session_id' => (string) Str::uuid(), 'position' => 1,
            'media_id' => $media->id, 'filename' => 'original.mp4', 'upload_identity' => (string) Str::uuid(),
            'backup_disk' => 'local', 'backup_path' => 'backup.mp4', 'backup_hash' => hash('sha256', 'backup'), 'backup_size' => 6,
            'state' => 'backed_up',
        ], $attributes));
    }

    public function test_35_confirmed_delete_reindexes_without_losing_other_mappings(): void
    {
        $file = $this->baseline('wl35:fan');
        Wl35DeviceMedia::create(['device_id' => 'fan', 'media_id' => $file->media_id, 'video_index' => 1, 'upload_identity' => $file->upload_identity]);
        $second = Wl35DeviceMedia::create(['device_id' => 'fan', 'video_index' => 2, 'upload_identity' => (string) Str::uuid()]);
        $fleet = $this->mock(UnifiedFleetClient::class);
        $fleet->shouldReceive('getFleet')->once()->andReturn(['devices' => [['type' => 'wl35', 'id' => 'fan', 'online' => true, 'session_ready' => true, 'video_count' => 2]]]);
        $fleet->shouldReceive('deleteWl35Video')->once()->with('fan', 1, 2)->andReturn(['confirmed' => true, 'deletion_status' => 'confirmed', 'video_count' => 1]);
        app(CampaignDeviceTransport::class)->removeBaseline($file);
        $this->assertSame('removed', $file->fresh()->state);
        $this->assertSame(1, $second->fresh()->video_index);
    }

    public function test_35_ambiguous_delete_is_not_retried(): void
    {
        $file = $this->baseline('wl35:fan');
        Wl35DeviceMedia::create(['device_id' => 'fan', 'media_id' => $file->media_id, 'video_index' => 1, 'upload_identity' => $file->upload_identity]);
        $fleet = $this->mock(UnifiedFleetClient::class);
        $fleet->shouldReceive('getFleet')->once()->andReturn(['devices' => [['type' => 'wl35', 'id' => 'fan', 'online' => true, 'session_ready' => true, 'video_count' => 1]]]);
        $fleet->shouldReceive('deleteWl35Video')->once()->andThrow(new \RuntimeException('timeout'));
        $transport = app(CampaignDeviceTransport::class);
        $transport->removeBaseline($file);
        $transport->removeBaseline($file->fresh());
        $this->assertSame('needs_review', $file->fresh()->state);
    }

    public function test_35_interrupted_upload_is_not_retried(): void
    {
        $file = $this->baseline('wl35:fan', ['state' => 'restoring']);
        $this->mock(UnifiedFleetClient::class)->shouldNotReceive('uploadPathAndDistribute');
        app(CampaignDeviceTransport::class)->restoreBaselineFile($file, Storage::disk('local')->path('backup.mp4'));
        $this->assertSame('needs_review', $file->fresh()->state);
    }

    public function test_35_interrupted_delete_is_not_retried(): void
    {
        $file = $this->baseline('wl35:fan', ['state' => 'removing']);
        $this->mock(UnifiedFleetClient::class)->shouldNotReceive('deleteWl35Video');
        app(CampaignDeviceTransport::class)->removeBaseline($file);
        $this->assertSame('needs_review', $file->fresh()->state);
    }

    public function test_35_restores_backup_and_tracks_new_installation_identity(): void
    {
        $file = $this->baseline('wl35:fan', ['state' => 'removed']);
        $fleet = $this->mock(UnifiedFleetClient::class);
        $fleet->shouldReceive('getFleet')->once()->andReturn(['devices' => [['type' => 'wl35', 'id' => 'fan', 'online' => true, 'fan_connected' => true, 'session_ready' => true, 'status_fresh' => true, 'restart_barrier' => true, 'restart_pending' => false, 'boot_id' => 'boot-1', 'uploading' => false, 'video_count' => 0]]]);
        $fleet->shouldReceive('uploadPathAndDistribute')->once()->andReturn(['results' => [['key' => 'wl35:fan', 'success' => true, 'video_index' => 1, 'upload_boot_id' => 'boot-1']]]);
        app(CampaignDeviceTransport::class)->restoreBaselineFile($file, Storage::disk('local')->path('backup.mp4'));
        $this->assertSame('restored', $file->fresh()->state);
        $this->assertSame(Wl35DeviceMedia::sole()->upload_identity, $file->fresh()->operation['restored_upload_identity']);
        $this->assertSame(1, $file->fresh()->operation['restored_index']);
    }

    public function test_35_restores_an_identified_video_without_a_saved_filename(): void
    {
        $file = $this->baseline('wl35:fan', ['state' => 'removed', 'filename' => null]);
        $fleet = $this->mock(UnifiedFleetClient::class);
        $fleet->shouldReceive('getFleet')->once()->andReturn(['devices' => [['type' => 'wl35', 'id' => 'fan', 'online' => true, 'fan_connected' => true, 'session_ready' => true, 'status_fresh' => true, 'restart_barrier' => true, 'restart_pending' => false, 'boot_id' => 'boot-1', 'uploading' => false, 'video_count' => 0]]]);
        $fleet->shouldReceive('uploadPathAndDistribute')->once()
            ->withArgs(fn ($path, $name, $targets, $progress, $play) => $name === 'campaign-baseline-'.$file->id.'.mp4' && $play === false)
            ->andReturn(['results' => [['key' => 'wl35:fan', 'success' => true, 'video_index' => 1, 'upload_boot_id' => 'boot-1']]]);
        app(CampaignDeviceTransport::class)->restoreBaselineFile($file, Storage::disk('local')->path('backup.mp4'));
        $this->assertSame('restored', $file->fresh()->state);
        $this->assertSame('campaign-baseline-'.$file->id.'.mp4', Wl35DeviceMedia::sole()->filename);
    }

    public function test_42_removal_requires_matching_report_and_keeps_same_request_id(): void
    {
        $file = $this->baseline('z2:AABBCCDDEEFF');
        $id = null;
        Http::fake(function ($request) use (&$id) {
            if ($request->method() === 'POST') {
                $id = $request['request_id'];

                return Http::response(['result' => 0]);
            }

            return Http::response(['device' => ['reportedPlaylistAt' => now()->getTimestampMs(), 'mediaRemovals' => $id ? [['requestId' => $id, 'status' => 'reported_absent', 'physicalConfirmed' => false]] : []]]);
        });
        $transport = app(CampaignDeviceTransport::class);
        $transport->removeBaseline($file);
        $this->assertSame('removing', $file->fresh()->state);
        $transport->removeBaseline($file->fresh());
        $this->assertSame('removed', $file->fresh()->state);
        $this->assertStringContainsString('no está confirmado', $file->fresh()->last_error);
    }

    public function test_42_restores_copy_and_waits_for_new_report(): void
    {
        $file = $this->baseline('z2:AABBCCDDEEFF', ['state' => 'removed']);
        $reported = false;
        Http::fake(function ($request) use (&$reported) {
            if (str_contains($request->url(), '/api/media/upload')) {
                return Http::response(['result' => 0, 'filename' => 'restored.mp4']);
            }
            if ($request->method() === 'POST') {
                return Http::response(['result' => 0]);
            }

            return Http::response(['device' => [
                'reportedPlaylistAt' => now()->getTimestampMs(),
                'reportedPlaylistVersion' => $reported ? 2 : 1,
                'reportedPlaylist' => $reported ? ['restored.mp4'] : [],
            ]]);
        });
        $transport = app(CampaignDeviceTransport::class);
        $path = Storage::disk('local')->path('backup.mp4');
        $transport->restoreBaselineFile($file, $path);
        $this->assertSame('restoring', $file->fresh()->state);
        $this->travel(1)->seconds();
        $reported = true;
        $transport->restoreBaselineFile($file->fresh(), $path);
        $this->assertSame('restored', $file->fresh()->state);
        $this->assertSame('restored.mp4', $file->fresh()->operation['restored_filename']);
    }

    public function test_42_unconfirmed_restoration_requires_review_without_resending(): void
    {
        $file = $this->baseline('z2:AABBCCDDEEFF', [
            'state' => 'restoring',
            'operation' => ['stage' => 'sending', 'current_filename' => 'restored.mp4', 'report_version' => 1, 'sent_at_ms' => now()->subMinutes(6)->getTimestampMs()],
        ]);
        Http::fake(['*' => Http::response(['device' => ['reportedPlaylistAt' => now()->getTimestampMs(), 'reportedPlaylistVersion' => 2, 'reportedPlaylist' => []]])]);
        app(CampaignDeviceTransport::class)->restoreBaselineFile($file, Storage::disk('local')->path('backup.mp4'));
        $this->assertSame('needs_review', $file->fresh()->state);
        Http::assertSentCount(1);
    }
}
