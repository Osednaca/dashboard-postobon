<?php

namespace Tests\Feature;

use App\Jobs\ProcessCampaignDeviceJob;
use App\Models\Campaign;
use App\Models\CampaignDeviceFile;
use App\Models\CampaignDevicePlayback;
use App\Models\Media;
use App\Models\Wl35DeviceMedia;
use App\Services\Campaigns\CampaignDeviceTransport;
use App\Services\Campaigns\CampaignLifecycle;
use App\Services\CampaignTargetCatalog;
use App\Services\Fleet\DeviceMutationLock;
use App\Services\Fleet\MediaPreviewSource;
use App\Services\Fleet\MediaSourceMaterializer;
use App\Services\Fleet\UnifiedFleetClient;
use App\Services\Fleet\VideoRemovalRejected;
use App\Services\Z2\Z2VideoService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CampaignPlaybackRestorationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private string $target = 'z2:AABBCCDDEEFF';

    private array $device;

    private array $commands = [];

    private bool $offline = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime();
        Http::preventStrayRequests();
        Queue::fake();
        config(['privatecloud.base_url' => 'http://cloud.test', 'cache.default' => 'database', 'queue.default' => 'database', 'queue.connections.database.retry_after' => 2000]);
        $this->mock(CampaignTargetCatalog::class)->shouldReceive('validatedKeys')->andReturnUsing(fn ($keys) => $keys);
        $this->mock(MediaPreviewSource::class)->shouldReceive('resolve')->andReturnUsing(fn ($media) => ['kind' => 'private_cloud', 'path' => '/fileDownload/Videos/'.basename($media->file_path)]);
        $this->device = ['reportedPlaylist' => ['original.mp4'], 'reportedPlaylistVersion' => 1, 'displayImageId' => 'original.mp4'];
        Http::fake(function ($request) {
            if ($this->offline) {
                return Http::response(['result' => -1], 503);
            }
            if ($request->method() === 'GET') {
                return Http::response(['device' => $this->device + ['reportedPlaylistAt' => now()->getTimestampMs()]]);
            }
            $action = basename(parse_url($request->url(), PHP_URL_PATH));
            $this->commands[] = [$action, $request['filename']];
            if ($action === 'play') {
                // The snapshot must already be durable before any installation side effect.
                $this->assertNotNull(CampaignDevicePlayback::sole()->baseline);
                $this->device['reportedPlaylist'][] = $request['filename'];
                $this->device['reportedPlaylistVersion']++;
                $this->device['displayImageId'] = $request['filename'];
            } elseif ($action === 'remove-media') {
                $this->device['reportedPlaylist'] = array_values(array_diff($this->device['reportedPlaylist'], [$request['filename']]));
                $this->device['mediaRemovals'][] = ['requestId' => $request['request_id'], 'status' => 'reported_absent'];
            } elseif ($action === 'select-media') {
                $this->device['displayImageId'] = $request['filename'];
            }

            return Http::response(['result' => 0, 'delivered' => 'queued']);
        });
    }

    private function campaign(string $name = 'campaign.mp4', array $attributes = []): Campaign
    {
        $campaign = Campaign::factory()->create($attributes + ['status' => 'draft', 'start_date' => now()->subMinute(), 'end_date' => now()->addHour(), 'target_devices' => [$this->target], 'priority' => 1]);
        $media = Media::factory()->video()->create(['duration' => 10, 'file_path' => $name]);
        $campaign->media()->attach($media, ['order' => 1]);
        app(CampaignLifecycle::class)->activate($campaign);

        return $campaign;
    }

    private function process(int $times = 1): void
    {
        for ($i = 0; $i < $times; $i++) {
            $this->travel(1)->seconds();
            app(CampaignLifecycle::class)->processDevice($this->target);
        }
    }

    public function test_real_lifecycle_preserves_old_files_and_returns_to_previous_selection_after_cleanup(): void
    {
        $campaign = $this->campaign();
        $this->process(2);
        $this->assertSame('ready', CampaignDeviceFile::sole()->state);
        $this->assertSame('campaign.mp4', $this->device['displayImageId']);
        app(CampaignLifecycle::class)->finish($campaign);
        $this->process(3);
        $this->assertSame(['original.mp4'], $this->device['reportedPlaylist']);
        $this->assertSame('original.mp4', $this->device['displayImageId']);
        $this->assertSame('restored', CampaignDevicePlayback::sole()->restore_state);
        $this->assertNotNull(CampaignDevicePlayback::sole()->baseline_closed_at);
        $this->assertNotContains(['remove-media', 'original.mp4'], $this->commands);
        Http::assertNotSent(fn ($r) => str_ends_with($r->url(), '/power'));
    }

    public function test_pause_restores_previous_selection_and_resume_keeps_the_original_snapshot(): void
    {
        $campaign = $this->campaign();
        $this->process(2);
        $baseline = CampaignDevicePlayback::sole()->baseline;
        $campaign->update(['status' => 'paused']);
        $this->process();
        $this->assertSame('original.mp4', $this->device['displayImageId']);
        $this->assertNull(CampaignDevicePlayback::sole()->baseline_closed_at);
        $this->assertSame('ready', CampaignDeviceFile::sole()->state);
        app(CampaignLifecycle::class)->activate($campaign);
        $this->process();
        $this->assertSame('campaign.mp4', $this->device['displayImageId']);
        $this->assertSame($baseline, CampaignDevicePlayback::sole()->baseline);
    }

    public function test_second_campaign_does_not_replace_snapshot_or_restore_while_another_is_active(): void
    {
        $first = $this->campaign('first.mp4', ['priority' => 1]);
        $second = $this->campaign('second.mp4', ['priority' => 9]);
        $this->process(4);
        $this->assertSame('second.mp4', $this->device['displayImageId']);
        $this->assertSame(['original.mp4'], CampaignDevicePlayback::sole()->baseline['files']);
        app(CampaignLifecycle::class)->finish($second);
        $this->process(3);
        $this->assertSame('first.mp4', $this->device['displayImageId']);
        $this->assertNotContains(['select-media', 'original.mp4'], $this->commands);
        app(CampaignLifecycle::class)->finish($first);
        $this->process(3);
        $this->assertSame('original.mp4', $this->device['displayImageId']);
    }

    public function test_preexisting_campaign_file_is_preserved_even_when_referenced_by_aliases(): void
    {
        $first = $this->campaign('original.mp4');
        $second = $this->campaign('alias/original.mp4');
        $this->process(2);
        $this->assertFalse(CampaignDeviceFile::sole()->owned);
        app(CampaignLifecycle::class)->finish($first);
        $this->process();
        $this->assertSame('ready', CampaignDeviceFile::sole()->state);
        app(CampaignLifecycle::class)->finish($second);
        $this->process(2);
        $this->assertSame('preserved', CampaignDeviceFile::sole()->state);
        $this->assertSame(['original.mp4'], $this->device['reportedPlaylist']);
        $this->assertNotContains(['remove-media', 'original.mp4'], $this->commands);
    }

    public function test_legacy_campaign_has_no_invented_baseline_and_does_not_delete_reused_files(): void
    {
        $campaign = $this->campaign();
        CampaignDeviceFile::sole()->update(['state' => 'ready', 'owned' => true]);
        app(CampaignLifecycle::class)->finish($campaign);
        $this->process(2);
        $this->assertSame('legacy', CampaignDevicePlayback::sole()->baseline['type']);
        $this->assertSame('unavailable', CampaignDevicePlayback::sole()->restore_state);
        $this->assertStringContainsString('No se registró', CampaignDevicePlayback::sole()->restore_error);
        $this->assertSame('preserved', CampaignDeviceFile::sole()->state);
        $this->assertSame([], $this->commands);
    }

    public function test_offline_restoration_remains_pending_and_tick_retries_after_all_files_are_terminal(): void
    {
        $campaign = $this->campaign('original.mp4');
        $this->process(2);
        app(CampaignLifecycle::class)->finish($campaign);
        $this->process(); // Marks reused file preserved; restoration is next tick.
        $this->offline = true;
        $this->process();
        $this->assertSame('pending', CampaignDevicePlayback::sole()->restore_state);
        $this->assertNotNull(CampaignDevicePlayback::sole()->restore_error);
        app(CampaignLifecycle::class)->tick();
        Queue::assertPushed(ProcessCampaignDeviceJob::class, fn ($job) => $job->target === $this->target);
        $this->offline = false;
        $this->process();
        $this->assertSame('restored', CampaignDevicePlayback::sole()->restore_state);
        $this->assertNull(CampaignDevicePlayback::sole()->restore_error);
    }

    public function test_empty_baseline_does_not_select_an_arbitrary_video(): void
    {
        $this->device['reportedPlaylist'] = [];
        $this->device['displayImageId'] = null;
        $campaign = $this->campaign();
        $this->process(2);
        app(CampaignLifecycle::class)->finish($campaign);
        $this->commands = [];
        $this->process(3);
        $this->assertSame('empty', CampaignDevicePlayback::sole()->restore_state);
        $this->assertFalse(collect($this->commands)->contains(fn ($command) => $command[0] === 'select-media'));
    }

    public function test_missing_previous_selection_is_visible_and_files_remain_intact(): void
    {
        $this->device['displayImageId'] = 'unknown.mp4';
        $campaign = $this->campaign();
        $this->process(2);
        app(CampaignLifecycle::class)->finish($campaign);
        $this->process(3);
        $this->assertSame('unavailable', CampaignDevicePlayback::sole()->restore_state);
        $this->assertSame(['original.mp4'], $this->device['reportedPlaylist']);
        $this->assertNotContains(['select-media', 'unknown.mp4'], $this->commands);
    }

    public function test_inflight_external_removal_prevents_capture_and_any_campaign_command(): void
    {
        $this->device['mediaRemovals'] = [['status' => 'pending']];
        $this->campaign();
        $this->process();
        $this->assertNull(CampaignDevicePlayback::sole()->baseline);
        $this->assertSame('pending', CampaignDeviceFile::sole()->state);
        $this->assertSame('Espera a que termine la operación de contenido del equipo 42 antes de iniciar la campaña.', CampaignDevicePlayback::sole()->last_error);
        $this->assertSame([], $this->commands);
    }

    public function test_completed_video_with_cleared_intent_allows_capture_without_mutating_provider(): void
    {
        $this->device['desiredVideo'] = null;
        $this->device['updateImageStatus'] = 0;
        $baseline = app(CampaignDeviceTransport::class)->captureBaseline($this->target);
        $this->assertSame('original.mp4', $baseline['selection']);
        $this->assertSame(['original.mp4'], $baseline['files']);
        $this->assertNull($this->device['desiredVideo']);
        $this->assertSame([], $this->commands);
        Http::assertSentCount(1);
    }

    public function test_pending_video_intent_blocks_campaign_commands(): void
    {
        $this->device['desiredVideo'] = 'next.mp4';
        $this->device['updateImageStatus'] = 0;
        $this->campaign();
        $this->process();
        $this->assertNull(CampaignDevicePlayback::sole()->baseline);
        $this->assertSame('pending', CampaignDeviceFile::sole()->state);
        $this->assertSame([], $this->commands);
    }

    public function test_same_selected_video_still_downloading_does_not_allow_capture(): void
    {
        $this->device['desiredVideo'] = 'original.mp4';
        $this->device['updateImageStatus'] = 1;
        $this->campaign();
        $this->process();
        $this->assertNull(CampaignDevicePlayback::sole()->baseline);
        $this->assertSame('pending', CampaignDeviceFile::sole()->state);
        $this->assertSame([], $this->commands);
    }

    public function test_same_filename_intent_waits_for_heartbeat_confirmation_even_with_recent_report(): void
    {
        $this->device['desiredVideo'] = 'original.mp4';
        $this->device['updateImageStatus'] = 0;
        $this->campaign();
        $this->process();
        $this->assertNull(CampaignDevicePlayback::sole()->baseline);
        $this->assertSame([], $this->commands);
    }

    public function test_snapshot_survives_new_service_instance_and_new_campaign_after_closure_gets_fresh_snapshot(): void
    {
        $first = $this->campaign();
        $this->process(2);
        $baseline = CampaignDevicePlayback::sole()->baseline;
        $this->app->forgetInstance(CampaignLifecycle::class);
        $this->assertSame($baseline, CampaignDevicePlayback::sole()->baseline);
        app(CampaignLifecycle::class)->finish($first);
        $this->process(3);
        $this->device['reportedPlaylist'] = ['replacement.mp4'];
        $this->device['displayImageId'] = 'replacement.mp4';
        $this->campaign('next.mp4');
        $this->process();
        $this->assertSame('replacement.mp4', CampaignDevicePlayback::sole()->baseline['selection']);
    }

    public function test_wl35_restores_saved_index_without_needing_its_original_file(): void
    {
        $fleet = $this->mock(UnifiedFleetClient::class);
        $fleet->shouldReceive('getFleet')->twice()->andReturn(['devices' => [['type' => 'wl35', 'id' => 'fan', 'online' => true, 'session_ready' => true, 'video_count' => 2, 'current_video' => 2]]]);
        $fleet->shouldReceive('sendCommand')->once()->with(['command' => 'play', 'targets' => ['wl35:fan'], 'wl35_video_index' => 2])->andReturn(['results' => [['key' => 'wl35:fan', 'success' => true]]]);
        $transport = app(CampaignDeviceTransport::class);
        $baseline = $transport->captureBaseline('wl35:fan');
        $this->assertSame(2, $baseline['selection']);
        $this->assertTrue($transport->restoreBaseline('wl35:fan', $baseline));
    }

    public function test_wl35_changed_identity_prevents_restoring_reassigned_index(): void
    {
        Wl35DeviceMedia::create(['device_id' => 'fan', 'video_index' => 1, 'upload_identity' => 'new']);
        $fleet = $this->mock(UnifiedFleetClient::class);
        $fleet->shouldReceive('getFleet')->once()->andReturn(['devices' => [['type' => 'wl35', 'id' => 'fan', 'online' => true, 'session_ready' => true, 'video_count' => 1]]]);
        $fleet->shouldNotReceive('sendCommand');
        $this->expectExceptionMessage('identidad');
        app(CampaignDeviceTransport::class)->restoreBaseline('wl35:fan', ['type' => 'wl35', 'selection' => 1, 'count' => 1, 'upload_identity' => 'old']);
    }

    public function test_wl35_real_lifecycle_appends_cleans_up_and_restores_original_index_without_powering_off(): void
    {
        $this->target = 'wl35:fan';
        Storage::fake('local');
        Wl35DeviceMedia::create(['device_id' => 'fan', 'video_index' => 1, 'upload_identity' => 'original-installation']);
        $this->mock(MediaSourceMaterializer::class)->shouldReceive('materialize')->once()->andReturnUsing(function ($media, $path) {
            Storage::disk('local')->put($path, 'fixture');

            return 7;
        });
        $count = 1;
        $selected = 1;
        $fleet = $this->mock(UnifiedFleetClient::class);
        $fleet->shouldReceive('getFleet')->andReturnUsing(function () use (&$count, &$selected) {
            return ['devices' => [['type' => 'wl35', 'id' => 'fan', 'online' => true, 'session_ready' => true, 'video_count' => $count, 'current_video' => $selected]]];
        });
        $fleet->shouldReceive('uploadPathAndDistribute')->once()->andReturnUsing(function () use (&$count) {
            $this->assertSame(1, CampaignDevicePlayback::sole()->baseline['selection']);
            $count++;

            return ['results' => [['key' => $this->target, 'type' => 'wl35', 'id' => 'fan', 'success' => true, 'video_index' => $count]]];
        });
        $fleet->shouldReceive('sendCommand')->twice()->andReturnUsing(function ($payload) use (&$selected) {
            $this->assertSame('play', $payload['command']);
            $selected = $payload['wl35_video_index'];

            return ['results' => [['key' => $this->target, 'success' => true]]];
        });
        $fleet->shouldReceive('deleteWl35Video')->once()->with('fan', 2, 2)->andReturnUsing(function () use (&$count) {
            $count--;

            return ['confirmed' => true, 'deletion_status' => 'confirmed', 'video_count' => $count];
        });
        $campaign = $this->campaign();
        $this->process(2);
        $this->assertSame(2, $selected);
        app(CampaignLifecycle::class)->finish($campaign);
        $this->process(2);
        $this->assertSame(1, $count);
        $this->assertSame(1, $selected);
        $this->assertSame('removed', CampaignDeviceFile::sole()->state);
        $this->assertSame('original-installation', Wl35DeviceMedia::sole()->upload_identity);
        $this->assertNotNull(CampaignDevicePlayback::sole()->baseline_closed_at);
    }

    public function test_alias_of_session_owned_file_is_cleaned_only_after_last_campaign_finishes(): void
    {
        $this->mock(UnifiedFleetClient::class)->shouldReceive('sendCommand')->andReturn(['results' => [['key' => $this->target, 'success' => true]]]);
        $first = $this->campaign();
        $this->process(2);
        $second = $this->campaign('second-source.mp4', ['priority' => 9]);
        $second->deliveries()->first()->file->update(['filename' => 'campaign.mp4']);
        $this->process(2);
        $this->assertSame(2, CampaignDeviceFile::where('owned', true)->count());
        app(CampaignLifecycle::class)->finish($first);
        $this->process();
        $this->assertNotContains(['remove-media', 'campaign.mp4'], $this->commands);
        app(CampaignLifecycle::class)->finish($second);
        $this->process(5);
        $this->assertSame(['original.mp4'], $this->device['reportedPlaylist']);
        $this->assertSame('original.mp4', $this->device['displayImageId']);
        $this->assertSame(0, CampaignDeviceFile::whereIn('state', ['ready', 'installing', 'removing'])->count());
    }

    public function test_external_content_mutations_are_blocked_while_baseline_is_protected(): void
    {
        $this->campaign();
        $this->process();
        $called = false;
        try {
            app(DeviceMutationLock::class)->run([$this->target], function () use (&$called) {
                $called = true;
            });
            $this->fail('Expected protection');
        } catch (VideoRemovalRejected $e) {
            $this->assertStringContainsString('sesión de campaña', $e->getMessage());
        }
        $this->assertFalse($called);
        $this->assertTrue(Cache::lock(CampaignLifecycle::lockName($this->target), 1)->get());
    }

    public function test_cloud_library_deletion_cannot_bypass_baseline_protection(): void
    {
        $this->campaign();
        $this->process();
        try {
            app(Z2VideoService::class)->deleteVideo('original.mp4');
            $this->fail('Expected library protection');
        } catch (VideoRemovalRejected $e) {
            $this->assertStringContainsString('protegido', $e->getMessage());
        }
        Http::assertNotSent(fn ($r) => $r->method() === 'DELETE');
    }
}
