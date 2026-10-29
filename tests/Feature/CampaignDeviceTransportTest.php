<?php

namespace Tests\Feature;

use App\Models\CampaignDeviceFile;
use App\Models\Media;
use App\Models\Wl35DeviceMedia;
use App\Services\Campaigns\CampaignDeviceTransport;
use App\Services\Fleet\MediaSourceMaterializer;
use App\Services\Fleet\UnifiedFleetClient;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CampaignDeviceTransportTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->freezeTime();
        config(['privatecloud.base_url' => 'http://cloud.test']);
        Storage::fake('local');
    }

    private function file(string $target = 'z2:AABBCCDDEEFF', array $attributes = []): CampaignDeviceFile
    {
        $media = Media::factory()->video()->create(['file_path' => 'campaign.mp4']);

        return CampaignDeviceFile::create(array_merge(['target_key' => $target, 'media_id' => $media->id, 'identity_key' => hash('sha256', 'campaign.mp4'), 'filename' => 'campaign.mp4', 'state' => 'pending'], $attributes));
    }

    private function report(array $attributes = []): array
    {
        return ['device' => array_merge(['reportedPlaylist' => [], 'reportedPlaylistAt' => now()->getTimestampMs(), 'reportedPlaylistVersion' => 4], $attributes)];
    }

    public function test_z2_waits_for_new_report_not_optimistic_playlist(): void
    {
        $file = $this->file();
        $gets = 0;
        Http::fake(function ($request) use (&$gets) {
            if ($request->method() === 'GET') {
                $gets++;

                return Http::response($this->report(['playlist' => ['campaign.mp4'], 'reportedPlaylist' => $gets >= 3 ? ['campaign.mp4'] : [], 'reportedPlaylistVersion' => $gets >= 3 ? 5 : 4]));
            }

            return Http::response(['result' => 0]);
        });
        $transport = app(CampaignDeviceTransport::class);
        $transport->install($file);
        $this->assertSame('installing', $file->fresh()->state);
        $transport->confirmInstallation($file);
        $this->assertSame('installing', $file->fresh()->state);
        $this->travel(2)->seconds();
        $transport->confirmInstallation($file);
        $this->assertSame('ready', $file->fresh()->state);
    }

    public function test_preexisting_identified_z2_content_is_associated_without_upload(): void
    {
        $file = $this->file();
        Http::fake(['*' => Http::response($this->report(['reportedPlaylist' => ['campaign.mp4']]))]);
        $transport = app(CampaignDeviceTransport::class);
        $transport->install($file);
        $this->assertTrue($file->fresh()->owned);
        $this->assertSame('ready', $file->fresh()->state);
        Http::assertSentCount(1);
    }

    public function test_z2_removal_matches_own_request_id_and_never_claims_physical_confirmation(): void
    {
        $file = $this->file(attributes: ['state' => 'ready', 'owned' => true]);
        $request = 'old';
        Http::fake(function ($r) use (&$request) {
            return Http::response(array_merge(['result' => 0], $this->report(['mediaRemovals' => [['filename' => 'campaign.mp4', 'requestId' => $request, 'status' => 'reported_absent', 'physicalConfirmed' => false]]])));
        });
        $transport = app(CampaignDeviceTransport::class);
        $transport->remove($file);
        $file->refresh();
        $request = $file->evidence['removal_request_id'];
        $this->assertSame('removing', $file->state);
        $transport->remove($file);
        $this->assertSame('reported_absent', $file->fresh()->state);
        $this->assertNull($file->fresh()->confirmed_at);
    }

    public function test_z2_select_uses_non_destructive_endpoint(): void
    {
        $file = $this->file(attributes: ['state' => 'ready']);
        Http::fake(['*' => Http::response(['result' => 0])]);
        app(CampaignDeviceTransport::class)->select($file);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/select-media') && $r['filename'] === 'campaign.mp4');
        Http::assertSentCount(1);
    }

    public function test_stale_z2_report_cannot_authorize_installation(): void
    {
        $file = $this->file();
        Http::fake(['*' => Http::response($this->report(['reportedPlaylistAt' => now()->subMinutes(3)->getTimestampMs()]))]);
        $this->expectException(\RuntimeException::class);
        app(CampaignDeviceTransport::class)->install($file);
    }

    public function test_wl35_ack_confirms_delete_and_renumbers_other_mapping(): void
    {
        $file = $this->file('wl35:fan', ['state' => 'ready', 'owned' => true, 'video_index' => 1]);
        $mapping = Wl35DeviceMedia::create(['device_id' => 'fan', 'media_id' => $file->media_id, 'video_index' => 1, 'filename' => 'campaign.mp4', 'upload_identity' => 'installation-1']);
        $file->update(['evidence' => ['mapping_id' => $mapping->id, 'upload_identity' => 'installation-1']]);
        $other = Media::factory()->create();
        Wl35DeviceMedia::create(['device_id' => 'fan', 'media_id' => $other->id, 'video_index' => 2, 'filename' => 'other.mp4']);
        $fleet = $this->mock(UnifiedFleetClient::class);
        $fleet->shouldReceive('getFleet')->once()->andReturn(['devices' => [['type' => 'wl35', 'id' => 'fan', 'online' => true, 'session_ready' => true, 'video_count' => 2]]]);
        $fleet->shouldReceive('deleteWl35Video')->once()->with('fan', 1, 2)->andReturn(['confirmed' => true, 'deletion_status' => 'confirmed', 'video_count' => 1]);
        app(CampaignDeviceTransport::class)->remove($file);
        $this->assertSame('removed', $file->fresh()->state);
        $this->assertSame(1, Wl35DeviceMedia::sole()->video_index);
    }

    public function test_wl35_timeout_invalidates_mapping_and_requires_review_without_retry(): void
    {
        $file = $this->file('wl35:fan', ['state' => 'ready', 'owned' => true, 'video_index' => 1]);
        $mapping = Wl35DeviceMedia::create(['device_id' => 'fan', 'media_id' => $file->media_id, 'video_index' => 1, 'filename' => 'campaign.mp4', 'upload_identity' => 'installation-1']);
        $file->update(['evidence' => ['mapping_id' => $mapping->id, 'upload_identity' => 'installation-1']]);
        $fleet = $this->mock(UnifiedFleetClient::class);
        $fleet->shouldReceive('getFleet')->once()->andReturn(['devices' => [['type' => 'wl35', 'id' => 'fan', 'online' => true, 'session_ready' => true, 'video_count' => 1]]]);
        $fleet->shouldReceive('deleteWl35Video')->once()->andThrow(new \RuntimeException('timeout'));
        app(CampaignDeviceTransport::class)->remove($file);
        $this->assertSame('needs_review', $file->fresh()->state);
        $this->assertNull(Wl35DeviceMedia::sole()->media_id);
    }

    public function test_interrupted_wl35_upload_is_not_repeated(): void
    {
        $file = $this->file('wl35:fan', ['state' => 'installing', 'owned' => true]);
        $this->mock(UnifiedFleetClient::class)->shouldNotReceive('uploadPathAndDistribute');
        app(CampaignDeviceTransport::class)->confirmInstallation($file);
        $this->assertSame('needs_review', $file->fresh()->state);
    }

    public function test_wl35_new_upload_of_same_media_cannot_be_deleted_for_old_campaign(): void
    {
        $file = $this->file('wl35:fan', ['state' => 'ready', 'owned' => true, 'video_index' => 1]);
        $old = Wl35DeviceMedia::create(['device_id' => 'fan', 'media_id' => null, 'video_index' => 1, 'filename' => null]);
        $file->update(['evidence' => ['mapping_id' => $old->id]]);
        Wl35DeviceMedia::create(['device_id' => 'fan', 'media_id' => $file->media_id, 'video_index' => 2, 'filename' => 'campaign.mp4']);
        $fleet = $this->mock(UnifiedFleetClient::class);
        $fleet->shouldReceive('getFleet')->once()->andReturn(['devices' => [['type' => 'wl35', 'id' => 'fan', 'online' => true, 'session_ready' => true, 'video_count' => 2]]]);
        $fleet->shouldNotReceive('deleteWl35Video');
        app(CampaignDeviceTransport::class)->remove($file);
        $this->assertSame('needs_review', $file->fresh()->state);
        $this->assertDatabaseCount('wl35_device_media', 2);
    }

    public function test_reused_mapping_row_with_new_installation_token_cannot_be_deleted(): void
    {
        $file = $this->file('wl35:fan', ['state' => 'ready', 'owned' => true, 'video_index' => 1]);
        $mapping = Wl35DeviceMedia::create(['device_id' => 'fan', 'media_id' => $file->media_id, 'video_index' => 1, 'filename' => 'campaign.mp4', 'upload_identity' => 'new-installation']);
        $file->update(['evidence' => ['mapping_id' => $mapping->id, 'upload_identity' => 'old-installation']]);
        $fleet = $this->mock(UnifiedFleetClient::class);
        $fleet->shouldReceive('getFleet')->once()->andReturn(['devices' => [['type' => 'wl35', 'id' => 'fan', 'online' => true, 'session_ready' => true, 'video_count' => 1]]]);
        $fleet->shouldNotReceive('deleteWl35Video');
        app(CampaignDeviceTransport::class)->remove($file);
        $this->assertSame('needs_review', $file->fresh()->state);
    }

    public function test_wl35_upload_records_identity_without_starting_playback(): void
    {
        $file = $this->file('wl35:fan');
        $this->mock(MediaSourceMaterializer::class)->shouldReceive('materialize')->once()->andReturnUsing(function ($media, $path) {
            Storage::disk('local')->put($path, 'fixture');

            return 7;
        });
        $fleet = $this->mock(UnifiedFleetClient::class);
        $fleet->shouldReceive('getFleet')->once()->andReturn(['devices' => [['type' => 'wl35', 'id' => 'fan', 'online' => true, 'session_ready' => true, 'video_count' => 0]]]);
        $fleet->shouldReceive('uploadPathAndDistribute')->once()->withArgs(fn ($path, $name, $targets, $progress, $play) => $play === false && $targets === ['wl35:fan'])->andReturn(['results' => [['key' => 'wl35:fan', 'type' => 'wl35', 'id' => 'fan', 'success' => true, 'video_index' => 1]]]);
        app(CampaignDeviceTransport::class)->install($file);
        $file->refresh();
        $this->assertSame('ready', $file->state);
        $this->assertNotEmpty($file->evidence['upload_identity']);
        $this->assertSame(Wl35DeviceMedia::sole()->upload_identity, $file->evidence['upload_identity']);
    }
}
