<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignBaselineFile;
use App\Models\CampaignDeviceFile;
use App\Models\CampaignDevicePlayback;
use App\Models\Media;
use App\Models\Wl35DeviceMedia;
use App\Services\Campaigns\CampaignLifecycle;
use App\Services\CampaignTargetCatalog;
use App\Services\Fleet\UnifiedFleetClient;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CampaignWl35RepeatLifecycleTest extends TestCase
{
    use LazilyRefreshDatabase;

    private int $count = 1;

    private int $selected = 1;

    private int $boot = 1;

    private array $commands = [];

    private array $deleted = [];

    private bool $repeatSucceeds = true;

    private $afterSelection = null;

    private Media $previous;

    private Media $advert;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime();
        Queue::fake();
        Http::preventStrayRequests();
        Storage::fake('local');
        config(['campaigns.backup_disk' => null, 'campaigns.backup_persistent' => false, 'cache.default' => 'database', 'queue.default' => 'database', 'queue.connections.database.retry_after' => 2000]);
        $this->mock(CampaignTargetCatalog::class)->shouldReceive('validatedKeys')->andReturnUsing(fn ($keys) => $keys);
        Storage::disk('local')->put('advert.mp4', 'campaign bytes');
        $this->previous = Media::factory()->video()->create(['file_path' => 'previous.mp4']);
        $this->advert = Media::factory()->video()->create(['file_path' => 'advert.mp4', 'duration' => 10]);
        Wl35DeviceMedia::create(['device_id' => 'fan', 'video_index' => 1, 'media_id' => $this->previous->id, 'filename' => 'previous.mp4', 'upload_identity' => null]);
        $fleet = $this->mock(UnifiedFleetClient::class);
        $fleet->shouldReceive('getFleet')->andReturnUsing(fn () => ['devices' => [['type' => 'wl35', 'id' => 'fan', 'online' => true, 'fan_connected' => true, 'session_ready' => true, 'status_fresh' => true, 'restart_barrier' => true, 'restart_pending' => false, 'boot_id' => 'boot-'.$this->boot, 'uploading' => false, 'video_count' => $this->count, 'current_video' => $this->selected]]]);
        $fleet->shouldReceive('uploadPathAndDistribute')->andReturnUsing(function () {
            $uploadBoot = 'boot-'.$this->boot;
            $this->count++;
            $this->boot++; // Simulate the device restart before the next reconciliation tick.

            return ['results' => [['key' => 'wl35:fan', 'type' => 'wl35', 'id' => 'fan', 'success' => true, 'video_index' => $this->count, 'upload_boot_id' => $uploadBoot]]];
        });
        $fleet->shouldReceive('deleteWl35Video')->andReturnUsing(function ($id, $index, $expected) {
            $this->assertSame($this->count, $expected);
            $this->deleted[] = $index;
            $this->count--;

            return ['confirmed' => true, 'deletion_status' => 'confirmed', 'video_count' => $this->count];
        });
        $fleet->shouldReceive('sendCommand')->andReturnUsing(function ($payload) {
            $this->commands[] = $payload;
            $this->selected = $payload['wl35_video_index'] ?? $this->selected;
            $item = ['key' => 'wl35:fan', 'success' => true];
            if ($payload['command'] === 'play_repeat_one') {
                $item += ['selection_confirmed' => true, 'repeat_command_sent' => $this->repeatSucceeds, 'repeat_confirmed' => false];
                if ($this->afterSelection) {
                    ($this->afterSelection)();
                    $this->afterSelection = null;
                }
            }

            return ['results' => [$item]];
        });
    }

    private function campaign(?Media $media = null): Campaign
    {
        $campaign = Campaign::factory()->create(['status' => 'draft', 'start_date' => now()->subMinute(), 'end_date' => now()->addHour(), 'target_devices' => ['wl35:fan']]);
        $campaign->media()->attach($media ?? $this->advert, ['order' => 1]);
        app(CampaignLifecycle::class)->activate($campaign);

        return $campaign;
    }

    private function process(int $count = 1): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->travel(1)->seconds();
            app(CampaignLifecycle::class)->processDevice('wl35:fan');
        }
    }

    public function test_campaign_preserves_unidentified_previous_video_without_backup_and_restores_on_pause(): void
    {
        $campaign = $this->campaign();
        $this->process(4);
        $this->assertSame(2, $this->count);
        $this->assertSame([], $this->deleted);
        $this->assertSame('play_repeat_one', end($this->commands)['command']);
        $this->assertSame(2, $this->selected);
        $campaign->update(['status' => 'paused']);
        $this->process(4);
        $this->assertSame([2], $this->deleted);
        $this->assertSame(1, $this->count);
        $this->assertSame(1, $this->selected);
        $this->assertSame('play', end($this->commands)['command']);
        $this->assertSame($this->previous->id, Wl35DeviceMedia::sole()->media_id);
    }

    public function test_repeat_failure_does_not_advance_cursor_and_retry_reselects(): void
    {
        $this->repeatSucceeds = false;
        $campaign = $this->campaign();
        $this->process(3);
        $playback = CampaignDevicePlayback::sole();
        $this->assertNull($playback->campaign_id);
        $this->assertNotNull($playback->last_error);
        $this->repeatSucceeds = true;
        $this->process(1);
        $this->assertSame($campaign->id, $playback->fresh()->campaign_id);
        $this->assertSame('play_repeat_one', end($this->commands)['command']);
    }

    public function test_pause_during_composed_order_does_not_record_an_active_cursor(): void
    {
        $campaign = $this->campaign();
        $this->afterSelection = fn () => $campaign->update(['status' => 'paused']);
        $this->process(2);
        $this->assertNull(CampaignDevicePlayback::sole()->campaign_id);
        $this->process(3);
        $this->assertSame([2], $this->deleted);
        $this->assertSame(1, $this->selected);
    }

    public function test_expiry_during_composed_order_does_not_record_an_active_cursor(): void
    {
        $campaign = $this->campaign();
        $this->afterSelection = fn () => $campaign->update(['end_date' => now()->subSecond()]);
        $this->process(2);
        $this->assertNull(CampaignDevicePlayback::sole()->campaign_id);
        $this->process(3);
        $this->assertSame([2], $this->deleted);
        $this->assertSame(1, $this->selected);
    }

    public function test_previous_video_without_identity_is_not_reused_as_campaign_content(): void
    {
        $this->campaign($this->previous);
        $this->process(3);
        $this->assertSame([], $this->deleted);
        $this->assertSame([], $this->commands);
        $this->assertStringContainsString('identidad de carga', CampaignDevicePlayback::sole()->last_error);
    }

    public function test_ambiguous_old_t15_operation_blocks_without_mutation(): void
    {
        $this->campaign();
        $this->process(1);
        $playback = CampaignDevicePlayback::sole();
        CampaignBaselineFile::create(['playback_id' => $playback->id, 'session_id' => $playback->baseline['session_id'], 'position' => 1, 'media_id' => $this->previous->id, 'original_index' => 1, 'upload_identity' => 'old', 'backup_disk' => 'local', 'backup_path' => 'backup', 'backup_hash' => hash('sha256', 'old'), 'backup_size' => 3, 'state' => 'removing']);
        $this->commands = [];
        $this->process();
        $this->assertSame([], $this->deleted);
        $this->assertSame([], $this->commands);
        $this->assertSame('needs_review', CampaignBaselineFile::sole()->state);
        $this->assertStringContainsString('requiere revisión', $playback->fresh()->last_error);
    }

    public function test_t15_removed_original_is_recovered_and_selection_tracks_identity_after_reindex(): void
    {
        $campaign = $this->campaign();
        $file = CampaignDeviceFile::sole();
        Wl35DeviceMedia::query()->delete();
        $campaignMapping = Wl35DeviceMedia::create(['device_id' => 'fan', 'video_index' => 1, 'media_id' => $this->advert->id, 'filename' => 'advert.mp4', 'upload_identity' => 'campaign-installation']);
        $file->update(['state' => 'ready', 'owned' => true, 'video_index' => 1, 'confirmed_at' => now(), 'evidence' => ['mapping_id' => $campaignMapping->id, 'upload_identity' => 'campaign-installation']]);
        $sessionId = (string) Str::uuid();
        $playback = CampaignDevicePlayback::create(['target_key' => 'wl35:fan', 'baseline' => ['type' => 'wl35', 'count' => 1, 'selection' => 1, 'upload_identity' => 'old-installation', 'session_id' => $sessionId], 'restore_state' => 'pending']);
        Storage::disk('local')->put('backup/old.mp4', 'old');
        CampaignBaselineFile::create(['playback_id' => $playback->id, 'session_id' => $sessionId, 'position' => 1, 'media_id' => $this->previous->id, 'filename' => 'old.mp4', 'original_index' => 1, 'upload_identity' => 'old-installation', 'backup_disk' => 'local', 'backup_path' => 'backup/old.mp4', 'backup_hash' => hash('sha256', 'old'), 'backup_size' => 3, 'state' => 'removed']);

        $this->process(4);
        $this->assertSame(2, $this->count);
        $this->assertSame('restored', CampaignBaselineFile::sole()->state);
        $this->assertTrue($playback->fresh()->baseline['t15_reconciled']);
        $this->assertSame(2, $playback->fresh()->baseline['selection']);
        $this->assertSame([], $this->deleted);
        $this->assertSame('play_repeat_one', end($this->commands)['command']);

        app(CampaignLifecycle::class)->finish($campaign);
        $this->process(4);
        $this->assertSame([1], $this->deleted);
        $this->assertSame(1, $this->count);
        $this->assertSame(1, $this->selected);
        $this->assertSame($this->previous->id, Wl35DeviceMedia::sole()->media_id);
    }

    public function test_shared_campaign_file_is_retained_until_last_campaign_finishes(): void
    {
        $first = $this->campaign();
        $second = $this->campaign();
        $this->process(5);
        $this->assertSame(2, $this->count);
        app(CampaignLifecycle::class)->finish($first);
        $this->process(3);
        $this->assertSame([], $this->deleted);
        $this->assertSame(2, $this->count);
        app(CampaignLifecycle::class)->finish($second);
        $this->process(4);
        $this->assertSame([2], $this->deleted);
        $this->assertSame(1, $this->count);
        $this->assertSame(1, $this->selected);
    }

    public function test_previous_file_used_by_two_campaigns_is_never_deleted(): void
    {
        Storage::disk('local')->put('previous.mp4', 'previous');
        Wl35DeviceMedia::sole()->update(['upload_identity' => 'previous-installation']);
        $first = $this->campaign($this->previous);
        $second = $this->campaign($this->previous);
        $this->process(4);
        app(CampaignLifecycle::class)->finish($first);
        $this->process(2);
        app(CampaignLifecycle::class)->finish($second);
        $this->process(4);
        $this->assertSame([], $this->deleted);
        $this->assertSame(1, $this->count);
        $this->assertSame('previous-installation', Wl35DeviceMedia::sole()->upload_identity);
    }

    public function test_pause_restores_previously_selected_video_even_when_it_is_campaign_content(): void
    {
        Storage::disk('local')->put('previous.mp4', 'previous');
        Wl35DeviceMedia::sole()->update(['upload_identity' => 'previous-installation']);
        $campaign = $this->campaign($this->previous);
        $this->process(4);
        $this->assertSame('play_repeat_one', end($this->commands)['command']);
        $campaign->update(['status' => 'paused']);
        $this->process(4);
        $this->assertSame([], $this->deleted);
        $this->assertSame(1, $this->count);
        $this->assertSame(1, $this->selected);
        $this->assertSame('play', end($this->commands)['command']);
        $this->assertSame('previous-installation', Wl35DeviceMedia::sole()->upload_identity);
    }

    public function test_each_campaign_video_uses_single_repeat_after_selection(): void
    {
        Storage::disk('local')->put('another.mp4', 'another');
        $another = Media::factory()->video()->create(['file_path' => 'another.mp4', 'duration' => 10]);
        $campaign = Campaign::factory()->create(['status' => 'draft', 'start_date' => now()->subMinute(), 'end_date' => now()->addHour(), 'target_devices' => ['wl35:fan']]);
        $campaign->media()->attach($this->advert, ['order' => 1]);
        $campaign->media()->attach($another, ['order' => 2]);
        app(CampaignLifecycle::class)->activate($campaign);
        $this->process(4);
        $this->assertSame(2, $this->selected);
        $this->travel(11)->seconds();
        $this->process();
        $this->assertSame(3, $this->selected);
        $plays = collect($this->commands)->where('command', 'play_repeat_one')->pluck('wl35_video_index')->all();
        $this->assertSame([2, 3], $plays);
        $this->assertSame([], $this->deleted);
    }

    public function test_partial_t15_backup_preserves_unbacked_original_prefix(): void
    {
        $sessionId = (string) Str::uuid();
        $playback = CampaignDevicePlayback::create(['target_key' => 'wl35:fan', 'baseline' => ['type' => 'wl35', 'count' => 2, 'selection' => 1, 'session_id' => $sessionId]]);
        CampaignBaselineFile::create(['playback_id' => $playback->id, 'session_id' => $sessionId, 'position' => 1, 'media_id' => $this->previous->id, 'original_index' => 1, 'upload_identity' => 'first', 'backup_disk' => 'local', 'backup_path' => 'backup', 'backup_hash' => hash('sha256', 'old'), 'backup_size' => 3, 'state' => 'backed_up']);
        $second = new CampaignDeviceFile(['target_key' => 'wl35:fan', 'video_index' => 2, 'evidence' => ['upload_identity' => 'second']]);
        $this->assertTrue($playback->preserves($second));
    }
}
