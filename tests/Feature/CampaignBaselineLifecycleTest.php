<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignDevicePlayback;
use App\Models\Media;
use App\Models\Wl35DeviceMedia;
use App\Services\Campaigns\CampaignLifecycle;
use App\Services\CampaignTargetCatalog;
use App\Services\Fleet\UnifiedFleetClient;
use App\Services\Fleet\VideoRemovalRejected;
use App\Services\Z2\Z2VideoService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CampaignBaselineLifecycleTest extends TestCase
{
    use LazilyRefreshDatabase;

    private string $target = 'z2:AABBCCDDEEFF';

    private array $device;

    private array $actions = [];

    private bool $offline = false;

    private bool $delayRestoration = false;

    private int $uploads = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime();
        Queue::fake();
        Http::preventStrayRequests();
        Storage::fake('local');
        Storage::fake('backup');
        config(['campaigns.backup_disk' => 'backup', 'campaigns.backup_persistent' => true, 'filesystems.disks.backup.driver' => 'local', 'privatecloud.base_url' => 'http://cloud.test', 'cache.default' => 'database', 'queue.default' => 'database', 'queue.connections.database.retry_after' => 2000]);
        $this->mock(CampaignTargetCatalog::class)->shouldReceive('validatedKeys')->andReturnUsing(fn ($keys) => $keys);
        $this->device = ['reportedPlaylist' => ['original.mp4'], 'playlist' => ['original.mp4'], 'reportedPlaylistVersion' => 1, 'displayImageId' => 'original.mp4', 'mediaRemovals' => []];
        Http::fake(function ($request) {
            if ($this->offline) {
                return Http::response(['result' => -1], 503);
            }
            if ($request->method() === 'GET') {
                return Http::response(['result' => 0, 'device' => $this->device + ['reportedPlaylistAt' => now()->getTimestampMs()]]);
            }
            $action = basename(parse_url($request->url(), PHP_URL_PATH));
            $this->actions[] = $action;
            if ($action === 'remove-media') {
                $this->device['reportedPlaylist'] = array_values(array_diff($this->device['reportedPlaylist'], [$request['filename']]));
                $this->device['playlist'] = $this->device['reportedPlaylist'];
                $this->device['reportedPlaylistVersion']++;
                $this->device['mediaRemovals'][] = ['requestId' => $request['request_id'], 'status' => 'reported_absent'];
            } elseif ($action === 'upload') {
                $this->uploads++;

                return Http::response(['result' => 0, 'filename' => $this->uploads === 1 ? 'restored.mp4' : 'restored-'.$this->uploads.'.mp4']);
            } elseif ($action === 'play') {
                if ($this->delayRestoration && $request['filename'] === 'restored.mp4') {
                    return Http::response(['result' => 0]);
                }
                $this->device['reportedPlaylist'][] = $request['filename'];
                $this->device['playlist'] = $this->device['reportedPlaylist'];
                $this->device['reportedPlaylistVersion']++;
                $this->device['displayImageId'] = $request['filename'];
            } elseif ($action === 'select-media') {
                $this->device['displayImageId'] = $request['filename'];
            }

            return Http::response(['result' => 0]);
        });
    }

    private function campaign(bool $withOriginal = true): Campaign
    {
        if ($withOriginal) {
            Storage::disk('local')->put('original.mp4', 'original bytes');
            Media::factory()->video()->create(['file_path' => 'original.mp4']);
        }
        $campaign = Campaign::factory()->create(['status' => 'draft', 'start_date' => now()->subMinute(), 'end_date' => now()->addHour(), 'target_devices' => [$this->target]]);
        $media = Media::where('file_path', 'http://cloud.test/fileDownload/Videos/campaign.mp4')->first()
            ?? Media::factory()->video()->create(['duration' => 10, 'file_path' => 'http://cloud.test/fileDownload/Videos/campaign.mp4']);
        $campaign->media()->attach($media, ['order' => 1]);
        app(CampaignLifecycle::class)->activate($campaign);

        return $campaign;
    }

    private function process(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->travel(1)->seconds();
            app(CampaignLifecycle::class)->processDevice($this->target);
        }
    }

    public function test_missing_original_blocks_all_physical_commands(): void
    {
        $this->campaign(false);
        $this->process(3);
        $this->assertSame([], $this->actions);
        $this->assertSame(['original.mp4'], $this->device['reportedPlaylist']);
        $this->assertStringContainsString('original.mp4', CampaignDevicePlayback::sole()->restore_error);
    }

    public function test_original_is_backed_up_removed_and_restored_after_campaign(): void
    {
        $campaign = $this->campaign();
        $this->process(1);
        $this->assertSame([], $this->actions);
        $this->process(5);
        $this->assertContains('remove-media', $this->actions);
        $this->assertSame(['campaign.mp4'], $this->device['reportedPlaylist']);
        app(CampaignLifecycle::class)->finish($campaign);
        $this->process(8);
        $this->assertSame(['restored.mp4'], $this->device['reportedPlaylist']);
        $this->assertSame('restored.mp4', $this->device['displayImageId']);
        $this->assertSame('restored', CampaignDevicePlayback::sole()->restore_state);
    }

    public function test_pause_restores_previous_video_and_resume_removes_it_again(): void
    {
        $campaign = $this->campaign();
        $this->process(6);
        $this->assertSame(['campaign.mp4'], $this->device['reportedPlaylist']);
        $campaign->update(['status' => 'paused']);
        $this->process(8);
        $this->assertSame(['restored.mp4'], $this->device['reportedPlaylist']);
        app(CampaignLifecycle::class)->activate($campaign);
        $this->process(8);
        $this->assertSame(['campaign.mp4'], $this->device['reportedPlaylist']);
    }

    public function test_restored_server_copy_cannot_be_deleted_during_paused_session(): void
    {
        $campaign = $this->campaign();
        $this->process(6);
        $campaign->update(['status' => 'paused']);
        $this->process(8);
        $this->assertSame(['restored.mp4'], $this->device['reportedPlaylist']);
        $this->expectException(VideoRemovalRejected::class);
        app(Z2VideoService::class)->deleteVideo('restored.mp4');
    }

    public function test_all_originals_are_backed_up_before_any_device_command(): void
    {
        $this->device['reportedPlaylist'][] = 'other.mp4';
        $this->device['playlist'][] = 'other.mp4';
        Storage::disk('local')->put('other.mp4', 'other bytes');
        Media::factory()->video()->create(['file_path' => 'other.mp4']);
        $this->campaign();
        $this->process(2);
        $this->assertSame([], $this->actions);
        $this->assertSame(['original.mp4', 'other.mp4'], $this->device['reportedPlaylist']);
        $this->process(1);
        $this->assertContains('remove-media', $this->actions);
    }

    public function test_all_previous_videos_are_uploaded_again_after_campaign(): void
    {
        $this->device['reportedPlaylist'][] = 'other.mp4';
        $this->device['playlist'][] = 'other.mp4';
        Storage::disk('local')->put('other.mp4', 'other bytes');
        Media::factory()->video()->create(['file_path' => 'other.mp4']);
        $campaign = $this->campaign();
        $this->process(10);
        $this->assertSame(['campaign.mp4'], $this->device['reportedPlaylist']);
        app(CampaignLifecycle::class)->finish($campaign);
        $this->process(12);
        $this->assertSame(['restored.mp4', 'restored-2.mp4'], $this->device['reportedPlaylist']);
        $this->assertSame('restored.mp4', $this->device['displayImageId']);
    }

    public function test_shared_campaign_file_is_not_removed_until_last_active_campaign_finishes(): void
    {
        $first = $this->campaign();
        $second = $this->campaign(false);
        $this->process(7);
        $this->assertSame(['campaign.mp4'], $this->device['reportedPlaylist']);
        app(CampaignLifecycle::class)->finish($first);
        $this->process(4);
        $this->assertSame(['campaign.mp4'], $this->device['reportedPlaylist']);
        app(CampaignLifecycle::class)->finish($second);
        $this->process(8);
        $this->assertSame(['restored.mp4'], $this->device['reportedPlaylist']);
    }

    public function test_pausing_one_campaign_retains_only_content_used_by_other_active_campaign(): void
    {
        $first = $this->campaign();
        $second = $this->campaign(false);
        $this->process(7);
        $first->update(['status' => 'paused']);
        $this->process(4);
        $this->assertSame(['campaign.mp4'], $this->device['reportedPlaylist']);
        $second->update(['status' => 'paused']);
        $this->process(8);
        $this->assertSame(['restored.mp4'], $this->device['reportedPlaylist']);
    }

    public function test_pausing_empty_baseline_cleans_campaign_files(): void
    {
        $this->device['reportedPlaylist'] = [];
        $this->device['playlist'] = [];
        $this->device['displayImageId'] = null;
        $campaign = $this->campaign(false);
        $this->process(5);
        $this->assertSame(['campaign.mp4'], $this->device['reportedPlaylist']);
        $campaign->update(['status' => 'paused']);
        $this->process(5);
        $this->assertSame([], $this->device['reportedPlaylist']);
        $this->assertSame('empty', CampaignDevicePlayback::sole()->restore_state);
    }

    public function test_paused_offline_device_stays_pending_and_cleans_after_reconnect(): void
    {
        $campaign = $this->campaign();
        $this->process(6);
        $this->offline = true;
        $campaign->update(['status' => 'paused']);
        $this->process(2);
        $this->assertSame(['campaign.mp4'], $this->device['reportedPlaylist']);
        $this->assertNotNull(CampaignDevicePlayback::sole()->last_error);
        $this->offline = false;
        $this->process(8);
        $this->assertSame(['restored.mp4'], $this->device['reportedPlaylist']);
    }

    public function test_offline_during_cleanup_retries_and_restores_after_reconnect(): void
    {
        $campaign = $this->campaign();
        $this->process(6);
        app(CampaignLifecycle::class)->finish($campaign);
        $this->offline = true;
        $this->process(2);
        $this->assertNotNull(CampaignDevicePlayback::sole()->last_error);
        $this->assertSame(['campaign.mp4'], $this->device['reportedPlaylist']);
        $this->offline = false;
        $this->process(8);
        $this->assertSame(['restored.mp4'], $this->device['reportedPlaylist']);
    }

    public function test_new_campaign_waits_for_inflight_restoration_before_removing_previous(): void
    {
        $campaign = $this->campaign();
        $this->process(6);
        app(CampaignLifecycle::class)->finish($campaign);
        $this->delayRestoration = true;
        $this->process(4);
        $this->assertDatabaseHas('campaign_baseline_files', ['state' => 'restoring']);
        $this->campaign(false);
        $this->process(2);
        $this->assertSame([], $this->device['reportedPlaylist']);
        $this->delayRestoration = false;
        $this->device['reportedPlaylist'][] = 'restored.mp4';
        $this->device['playlist'] = $this->device['reportedPlaylist'];
        $this->device['reportedPlaylistVersion']++;
        $this->device['displayImageId'] = 'restored.mp4';
        $this->process(8);
        $this->assertSame(['campaign.mp4'], $this->device['reportedPlaylist']);
    }

    public function test_model_35_preserves_previous_and_cleans_only_campaign_file_after_finish(): void
    {
        $this->target = 'wl35:fan';
        Storage::disk('local')->put('original.mp4', 'original');
        Storage::disk('local')->put('campaign.mp4', 'campaign');
        $old = Media::factory()->video()->create(['file_path' => 'original.mp4']);
        $media = Media::factory()->video()->create(['file_path' => 'campaign.mp4', 'duration' => 10]);
        Wl35DeviceMedia::create(['device_id' => 'fan', 'media_id' => $old->id, 'video_index' => 1, 'upload_identity' => 'previous']);
        $count = 1;
        $selected = 1;
        $boot = 1;
        $fleet = $this->mock(UnifiedFleetClient::class);
        $fleet->shouldReceive('getFleet')->andReturnUsing(function () use (&$count, &$selected, &$boot) {
            return ['devices' => [['type' => 'wl35', 'id' => 'fan', 'online' => true, 'fan_connected' => true, 'session_ready' => true, 'status_fresh' => true, 'restart_barrier' => true, 'restart_pending' => false, 'boot_id' => 'boot-'.$boot, 'uploading' => false, 'video_count' => $count, 'current_video' => $selected]]];
        });
        $fleet->shouldReceive('deleteWl35Video')->once()->andReturnUsing(function ($id, $index, $expected) use (&$count) {
            $this->assertSame(2, $index);
            $this->assertSame($count, $expected);
            $count--;

            return ['confirmed' => true, 'deletion_status' => 'confirmed', 'video_count' => $count];
        });
        $fleet->shouldReceive('uploadPathAndDistribute')->once()->andReturnUsing(function () use (&$count, &$boot) {
            $uploadBoot = 'boot-'.$boot;
            $count++;
            $boot++;

            return ['results' => [['key' => $this->target, 'type' => 'wl35', 'id' => 'fan', 'success' => true, 'video_index' => $count, 'upload_boot_id' => $uploadBoot]]];
        });
        $fleet->shouldReceive('sendCommand')->andReturnUsing(function ($payload) use (&$selected) {
            $this->assertContains($payload['command'], ['play', 'play_repeat_one']);
            $selected = $payload['wl35_video_index'];

            return ['results' => [['key' => $this->target, 'success' => true, 'selection_confirmed' => true, 'repeat_command_sent' => true, 'repeat_confirmed' => false]]];
        });
        $campaign = Campaign::factory()->create(['status' => 'draft', 'start_date' => now()->subMinute(), 'end_date' => now()->addHour(), 'target_devices' => [$this->target]]);
        $campaign->media()->attach($media, ['order' => 1]);
        app(CampaignLifecycle::class)->activate($campaign);
        $this->process(6);
        $this->assertSame(2, $count);
        $this->assertSame($old->id, Wl35DeviceMedia::where('video_index', 1)->sole()->media_id);
        $this->assertSame($media->id, Wl35DeviceMedia::where('video_index', 2)->sole()->media_id);
        app(CampaignLifecycle::class)->finish($campaign);
        $this->process(7);
        $this->assertSame(1, $count);
        $this->assertSame($old->id, Wl35DeviceMedia::sole()->media_id);
        $this->assertSame(1, $selected);
    }
}
