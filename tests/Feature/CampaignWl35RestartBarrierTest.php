<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignDeviceFile;
use App\Models\CampaignDevicePlayback;
use App\Models\Media;
use App\Services\Campaigns\CampaignLifecycle;
use App\Services\Campaigns\CampaignWl35RestartBarrier;
use App\Services\CampaignTargetCatalog;
use App\Services\Fleet\UnifiedFleetClient;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class CampaignWl35RestartBarrierTest extends TestCase
{
    use LazilyRefreshDatabase;

    private string $boot = 'boot-A';

    private bool $fresh = true;

    private bool $online = true;

    private bool $session = true;

    private bool $restartPending = false;

    private bool $capability = true;

    private int $count = 0;

    private int $uploads = 0;

    private array $commands = [];

    private string $response = 'success';

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime();
        Queue::fake();
        Http::preventStrayRequests();
        Storage::fake('local');
        config(['cache.default' => 'database', 'queue.default' => 'database', 'queue.connections.database.retry_after' => 2000]);
        $this->mock(CampaignTargetCatalog::class)->shouldReceive('validatedKeys')->andReturnUsing(fn ($keys) => $keys);
        $fleet = $this->mock(UnifiedFleetClient::class);
        $fleet->shouldReceive('getFleet')->andReturnUsing(fn () => ['devices' => [$this->device()]]);
        $fleet->shouldReceive('uploadPathAndDistribute')->andReturnUsing(function ($path, $name, $targets, $progress, $play, $expected, $beforeDispatch) {
            $this->assertSame($this->boot, $expected);
            $this->assertSame($expected, CampaignDevicePlayback::where('target_key', 'wl35:fan')->sole()->wl35_wait_boot_id);
            if ($this->response === 'preflight_failure') {
                throw new RuntimeException('local preparation failed');
            }
            $beforeDispatch();
            $this->uploads++;
            if ($this->response === 'timeout') {
                throw new RuntimeException('connection dropped');
            }
            if ($this->response === 'safe_rejection') {
                return ['results' => [['key' => 'wl35:fan', 'success' => false, 'retry_safe' => true, 'command_sent' => false, 'error_code' => 'wl35_boot_changed']]];
            }
            if ($this->response === 'malformed_rejection') {
                return ['results' => [['key' => 'wl35:fan', 'success' => false, 'retry_safe' => true, 'error_code' => 'wl35_boot_changed']]];
            }
            $this->count++;

            return ['results' => [['key' => 'wl35:fan', 'type' => 'wl35', 'id' => 'fan', 'success' => true, 'video_index' => $this->count, 'upload_boot_id' => $this->boot]]];
        });
        $fleet->shouldReceive('sendCommand')->andReturnUsing(function ($payload) {
            $this->commands[] = $payload;

            return ['results' => [['key' => 'wl35:fan', 'success' => true, 'selection_confirmed' => true, 'repeat_command_sent' => true, 'repeat_confirmed' => false]]];
        });
        $fleet->shouldReceive('deleteWl35Video')->andReturnUsing(function ($id, $index, $expected) {
            $this->commands[] = ['command' => 'delete', 'index' => $index];
            $this->count--;

            return ['confirmed' => true, 'deletion_status' => 'confirmed', 'video_count' => $this->count];
        });
    }

    private function device(): array
    {
        return ['type' => 'wl35', 'id' => 'fan', 'online' => $this->online, 'fan_connected' => $this->online,
            'session_ready' => $this->session, 'status_fresh' => $this->fresh, 'restart_barrier' => $this->capability,
            'restart_pending' => $this->restartPending, 'boot_id' => $this->boot, 'uploading' => false,
            'video_count' => $this->count, 'current_video' => $this->count ? 1 : null];
    }

    private function campaign(int $videos = 2): Campaign
    {
        $campaign = Campaign::factory()->create(['status' => 'draft', 'start_date' => now()->subMinute(), 'end_date' => now()->addHour(), 'target_devices' => ['wl35:fan']]);
        for ($i = 1; $i <= $videos; $i++) {
            $path = 'ad-'.$i.'.mp4';
            Storage::disk('local')->put($path, 'video '.$i);
            $media = Media::factory()->video()->create(['file_path' => $path, 'duration' => 10]);
            $campaign->media()->attach($media, ['order' => $i]);
        }
        app(CampaignLifecycle::class)->activate($campaign);

        return $campaign;
    }

    private function process(int $times = 1): void
    {
        for ($i = 0; $i < $times; $i++) {
            $this->travel(1)->seconds();
            app(CampaignLifecycle::class)->processDevice('wl35:fan');
        }
    }

    public function test_second_video_waits_for_new_boot_and_fresh_ready_status(): void
    {
        $this->campaign();
        $this->process();
        $this->assertSame(1, $this->uploads);
        $this->assertSame('ready', CampaignDeviceFile::orderBy('id')->first()->state);
        $this->assertSame('boot-A', CampaignDevicePlayback::sole()->wl35_wait_boot_id);
        $this->process(3); // Includes a network reconnect with the same boot ID.
        $this->assertSame(1, $this->uploads);
        $this->assertSame([], $this->commands);
        $this->boot = 'boot-B';
        $this->fresh = false;
        $this->process();
        $this->assertSame(1, $this->uploads);
        $this->fresh = true;
        $this->session = false;
        $this->process();
        $this->assertSame(1, $this->uploads);
        $this->session = true;
        $this->restartPending = true;
        $this->process();
        $this->assertSame(1, $this->uploads);
        $this->restartPending = false;
        $this->process();
        $this->assertSame(2, $this->uploads);
        $this->assertSame('boot-B', CampaignDevicePlayback::sole()->wl35_wait_boot_id);
    }

    public function test_timeout_and_service_restart_preserve_confirmed_upload_without_retransmitting(): void
    {
        $this->campaign();
        $this->process();
        $this->travel(5)->minutes();
        $this->app->forgetInstance(CampaignWl35RestartBarrier::class);
        $this->online = false;
        $this->process();
        $this->online = true;
        $this->process();
        $this->assertSame(1, $this->uploads);
        $this->assertSame('ready', CampaignDeviceFile::orderBy('id')->first()->fresh()->state);
        $this->assertSame('boot-A', CampaignDevicePlayback::sole()->wl35_wait_boot_id);
    }

    public function test_old_firmware_is_blocked_before_upload(): void
    {
        $this->capability = false;
        $this->campaign();
        $this->process();
        $this->assertSame(0, $this->uploads);
        $this->assertSame('pending', CampaignDeviceFile::orderBy('id')->first()->state);
        $this->assertStringContainsString('Actualiza el firmware', CampaignDevicePlayback::sole()->last_error);
    }

    public function test_certified_precommand_refusal_returns_to_pending_without_reboot_barrier(): void
    {
        $this->response = 'safe_rejection';
        $this->campaign();
        $this->process();
        $this->assertSame(1, $this->uploads);
        $this->assertSame('pending', CampaignDeviceFile::orderBy('id')->first()->state);
        $this->assertNull(CampaignDevicePlayback::sole()->wl35_wait_boot_id);
    }

    public function test_failure_before_distribution_can_retry_but_unknown_dispatched_failure_cannot(): void
    {
        $this->response = 'preflight_failure';
        $this->campaign();
        $this->process();
        $this->assertSame('pending', CampaignDeviceFile::orderBy('id')->first()->state);
        $this->assertNull(CampaignDevicePlayback::sole()->wl35_wait_boot_id);
        $this->response = 'timeout';
        $this->process();
        $this->assertSame('needs_review', CampaignDeviceFile::orderBy('id')->first()->fresh()->state);
        $this->assertSame('boot-A', CampaignDevicePlayback::sole()->wl35_wait_boot_id);
        $this->boot = 'boot-B';
        $this->response = 'success';
        $this->process(3);
        $this->assertSame(1, $this->uploads);
        $this->assertSame('needs_review', CampaignDeviceFile::orderBy('id')->first()->fresh()->state);
        $this->assertSame([], $this->commands);
    }

    public function test_malformed_precommand_refusal_is_not_treated_as_safe(): void
    {
        $this->response = 'malformed_rejection';
        $this->campaign();
        $this->process();
        $this->assertSame('needs_review', CampaignDeviceFile::orderBy('id')->first()->state);
        $this->assertSame('boot-A', CampaignDevicePlayback::sole()->wl35_wait_boot_id);
    }

    public function test_pause_during_wait_does_not_send_cleanup_until_reconnected(): void
    {
        $campaign = $this->campaign();
        $this->process();
        $campaign->update(['status' => 'paused']);
        $this->process(2);
        $this->assertSame([], $this->commands);
        $this->assertSame('ready', CampaignDeviceFile::orderBy('id')->first()->fresh()->state);
        $this->boot = 'boot-B';
        $this->process();
        $this->assertSame('delete', $this->commands[0]['command']);
        $this->assertSame(1, $this->uploads);
    }

    public function test_existing_uncertain_file_blocks_other_video_without_changing_its_state(): void
    {
        $this->campaign();
        $file = CampaignDeviceFile::orderBy('id')->first();
        $file->update(['state' => 'needs_review', 'last_error' => 'Carga anterior incierta']);
        $this->process(3);
        $this->assertSame(0, $this->uploads);
        $this->assertSame([], $this->commands);
        $this->assertSame('needs_review', $file->fresh()->state);
        $this->assertSame('Carga anterior incierta', $file->fresh()->last_error);
        $this->assertStringContainsString('requiere revisión', CampaignDevicePlayback::sole()->last_error);
    }

    public function test_interrupted_install_becomes_uncertain_and_blocks_next_video_in_same_tick(): void
    {
        $this->response = 'preflight_failure';
        $this->campaign();
        $this->process();
        $file = CampaignDeviceFile::orderBy('id')->first();
        $file->update(['state' => 'installing']);
        CampaignDevicePlayback::sole()->update(['wl35_wait_boot_id' => 'boot-A', 'wl35_wait_started_at' => now()]);
        $this->boot = 'boot-B';
        $this->response = 'success';

        $this->process();

        $this->assertSame('needs_review', $file->fresh()->state);
        $this->assertSame('pending', CampaignDeviceFile::orderBy('id')->skip(1)->first()->state);
        $this->assertSame(0, $this->uploads);
        $this->assertSame([], $this->commands);
        $this->assertStringContainsString('requiere revisión', CampaignDevicePlayback::sole()->last_error);
    }
}
