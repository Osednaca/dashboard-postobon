<?php

namespace Tests\Feature;

use App\Jobs\PrepareCampaignDurations;
use App\Jobs\ProcessCampaignDeviceJob;
use App\Models\Campaign;
use App\Models\CampaignDeviceFile;
use App\Models\CampaignDevicePlayback;
use App\Models\Device;
use App\Models\Media;
use App\Services\Campaigns\CampaignActivity;
use App\Services\Campaigns\CampaignDeviceTransport;
use App\Services\Campaigns\CampaignLifecycle;
use App\Services\CampaignTargetCatalog;
use App\Services\Fleet\MediaPreviewSource;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CampaignLifecycleTest extends TestCase
{
    use LazilyRefreshDatabase;

    private string $target = 'z2:AABBCCDDEEFF';

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Queue::fake();
        config(['cache.default' => 'database', 'queue.default' => 'database', 'queue.connections.database.retry_after' => 2000]);
        $this->freezeTime();
        $this->mock(CampaignTargetCatalog::class)->shouldReceive('validatedKeys')->andReturnUsing(fn ($keys) => $keys);
        $this->mock(MediaPreviewSource::class)->shouldReceive('resolve')->andReturnUsing(fn ($media) => ['kind' => 'private_cloud', 'path' => '/fileDownload/Videos/'.basename($media->file_path)]);
    }

    private function campaign(array $attributes = [], int $count = 2): Campaign
    {
        $campaign = Campaign::factory()->create(array_merge(['status' => 'draft', 'start_date' => now()->subMinute(), 'end_date' => now()->addHour(), 'target_devices' => [$this->target]], $attributes));
        foreach (Media::factory()->video()->count($count)->create(['duration' => 10]) as $i => $media) {
            $campaign->media()->attach($media, ['order' => $i + 1]);
        }

        return $campaign;
    }

    private function ready(Campaign $campaign, bool $owned = true): void
    {
        $campaign->deliveries->each(fn ($d) => $d->file->update(['state' => 'ready', 'owned' => $owned]));
    }

    public function test_all_media_are_snapshotted_in_order_and_repeated_activation_is_idempotent(): void
    {
        $campaign = $this->campaign(['target_devices' => [$this->target, 'wl35:other']]);
        $lifecycle = app(CampaignLifecycle::class);
        $lifecycle->activate($campaign);
        $lifecycle->activate($campaign);
        $this->assertDatabaseCount('campaign_deliveries', 4);
        $this->assertDatabaseCount('campaign_device_files', 4);
        $this->assertSame([1, 2], $campaign->deliveries()->whereHas('file', fn ($q) => $q->where('target_key', $this->target))->pluck('position')->all());
        Http::assertNothingSent();
    }

    public function test_start_is_scheduled_then_activated_and_end_expires_even_when_paused(): void
    {
        $campaign = $this->campaign(['start_date' => now()->addMinute(), 'end_date' => now()->addMinutes(2)]);
        $lifecycle = app(CampaignLifecycle::class);
        $lifecycle->activate($campaign);
        $this->assertSame('scheduled', $campaign->fresh()->status);
        $this->travel(61)->seconds();
        $lifecycle->tick();
        $this->assertSame('active', $campaign->fresh()->status);
        $campaign->update(['status' => 'paused']);
        $this->travel(61)->seconds();
        $lifecycle->tick();
        $this->assertSame('finished', $campaign->fresh()->status);
        Queue::assertPushed(ProcessCampaignDeviceJob::class);
    }

    public function test_permanent_campaign_does_not_expire(): void
    {
        $campaign = $this->campaign(['end_date' => null]);
        app(CampaignLifecycle::class)->activate($campaign);
        $this->travel(100)->days();
        app(CampaignLifecycle::class)->tick();
        $this->assertSame('active', $campaign->fresh()->status);
    }

    public function test_unknown_duration_defers_activation_without_claiming_active(): void
    {
        $campaign = $this->campaign();
        $campaign->media()->first()->update(['duration' => null]);
        $this->assertFalse(app(CampaignLifecycle::class)->activate($campaign));
        Queue::assertPushed(PrepareCampaignDurations::class);
        $this->assertSame('draft', $campaign->fresh()->status);
    }

    public function test_installation_is_serial_and_unconfirmed_file_prevents_next_upload(): void
    {
        $campaign = $this->campaign();
        $transport = $this->mock(CampaignDeviceTransport::class);
        $transport->shouldReceive('install')->once()->andReturnUsing(fn ($file) => $file->update(['state' => 'installing', 'owned' => true]));
        $transport->shouldReceive('confirmInstallation')->once();
        $lifecycle = app(CampaignLifecycle::class);
        $lifecycle->activate($campaign);
        $lifecycle->processDevice($this->target);
        $lifecycle->processDevice($this->target);
        $this->assertSame(1, CampaignDeviceFile::where('state', 'pending')->count());
    }

    public function test_late_job_after_finish_never_installs_pending_files(): void
    {
        $campaign = $this->campaign();
        $transport = $this->mock(CampaignDeviceTransport::class);
        $transport->shouldNotReceive('install');
        $lifecycle = app(CampaignLifecycle::class);
        $lifecycle->activate($campaign);
        $lifecycle->finish($campaign);
        $lifecycle->processDevice($this->target);
        $this->assertSame(2, CampaignDeviceFile::where('state', 'removed')->count());
    }

    public function test_shared_physical_filename_survives_until_both_campaigns_finish(): void
    {
        $first = $this->campaign([], 1);
        $second = $this->campaign([], 1);
        $second->media()->first()->update(['file_path' => 'alias/'.basename($first->media()->first()->file_path)]);
        $transport = $this->mock(CampaignDeviceTransport::class);
        $transport->shouldReceive('select')->once();
        $transport->shouldReceive('power')->with($this->target, false)->zeroOrMoreTimes();
        $transport->shouldReceive('remove')->once()->andReturnUsing(fn ($file) => $file->update(['state' => 'removed']));
        $lifecycle = app(CampaignLifecycle::class);
        $lifecycle->activate($first);
        $lifecycle->activate($second);
        $this->ready($first);
        $this->assertDatabaseCount('campaign_device_files', 1);
        $lifecycle->finish($first);
        $lifecycle->processDevice($this->target);
        $this->assertSame('ready', CampaignDeviceFile::sole()->state);
        $lifecycle->finish($second);
        $lifecycle->processDevice($this->target);
        $this->assertSame('removed', CampaignDeviceFile::sole()->state);
    }

    public function test_soft_deleted_campaign_keeps_cleanup_evidence(): void
    {
        $campaign = $this->campaign([], 1);
        $transport = $this->mock(CampaignDeviceTransport::class);
        $transport->shouldReceive('remove')->once()->andReturnUsing(fn ($file) => $file->update(['state' => 'removed']));
        $lifecycle = app(CampaignLifecycle::class);
        $lifecycle->activate($campaign);
        $this->ready($campaign);
        $campaign->delete();
        $lifecycle->processDevice($this->target);
        $this->assertDatabaseCount('campaign_deliveries', 1);
        $this->assertSame('removed', CampaignDeviceFile::sole()->state);
    }

    public function test_edits_release_old_files_without_losing_history(): void
    {
        $campaign = $this->campaign();
        $lifecycle = app(CampaignLifecycle::class);
        $lifecycle->activate($campaign);
        $campaign->media()->detach($campaign->media()->first()->id);
        $lifecycle->snapshot($campaign);
        $this->assertDatabaseCount('campaign_deliveries', 2);
        $this->assertSame(1, $campaign->deliveries()->whereNotNull('released_at')->count());
    }

    public function test_rotation_uses_duration_order_repeats_and_stops_when_paused(): void
    {
        $campaign = $this->campaign();
        $seen = [];
        $transport = $this->mock(CampaignDeviceTransport::class);
        $transport->shouldReceive('power')->once()->with($this->target, false);
        $transport->shouldReceive('power')->once()->with($this->target, true);
        $transport->shouldReceive('select')->times(4)->andReturnUsing(function ($file) use (&$seen) {
            $seen[] = $file->media_id;
        });
        $lifecycle = app(CampaignLifecycle::class);
        $lifecycle->activate($campaign);
        $this->ready($campaign);
        $lifecycle->processDevice($this->target);
        $lifecycle->processDevice($this->target);
        $this->travel(10)->seconds();
        $lifecycle->processDevice($this->target);
        $this->travel(10)->seconds();
        $lifecycle->processDevice($this->target);
        $ids = $campaign->media()->pluck('media.id')->all();
        $this->assertSame([$ids[0], $ids[1], $ids[0]], $seen);
        $campaign->update(['status' => 'paused']);
        $this->travel(10)->seconds();
        $lifecycle->processDevice($this->target);
        $this->assertNull(CampaignDevicePlayback::sole()->campaign_id);
        $this->assertTrue(CampaignDevicePlayback::sole()->powered_off);
        $lifecycle->activate($campaign);
        $lifecycle->processDevice($this->target);
        $this->assertFalse(CampaignDevicePlayback::sole()->powered_off);
    }

    public function test_priority_winner_does_not_delete_lower_priority_files(): void
    {
        $low = $this->campaign(['priority' => 1], 1);
        $high = $this->campaign(['priority' => 9], 1);
        $transport = $this->mock(CampaignDeviceTransport::class);
        $transport->shouldNotReceive('remove');
        $transport->shouldReceive('select')->once()->withArgs(fn ($file) => $file->media_id === $high->media()->first()->id);
        $lifecycle = app(CampaignLifecycle::class);
        $lifecycle->activate($low);
        $lifecycle->activate($high);
        $this->ready($low);
        $this->ready($high);
        $lifecycle->processDevice($this->target);
    }

    public function test_device_lock_prevents_parallel_work_and_activation_during_cleanup(): void
    {
        $campaign = $this->campaign();
        $lock = Cache::lock(CampaignLifecycle::lockName($this->target), 2000);
        $lock->get();
        try {
            app(CampaignLifecycle::class)->activate($campaign);
            $this->fail();
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('target_devices', $e->errors());
        } finally {
            $lock->release();
        }
    }

    public function test_retryable_connection_failure_is_visible_and_does_not_lose_delivery(): void
    {
        $campaign = $this->campaign([], 1);
        $transport = $this->mock(CampaignDeviceTransport::class);
        $transport->shouldReceive('install')->once()->andThrow(new \RuntimeException('Se necesita un reporte reciente del contenido del equipo 42.'));
        $lifecycle = app(CampaignLifecycle::class);
        $lifecycle->activate($campaign);
        $lifecycle->processDevice($this->target);
        $this->assertSame('pending', CampaignDeviceFile::sole()->state);
        $this->assertSame('Se necesita un reporte reciente del contenido del equipo 42.', CampaignDeviceFile::sole()->last_error);
    }

    public function test_tick_retries_reconciliation_when_a_device_is_busy_without_recording_a_false_error(): void
    {
        $campaign = $this->campaign([], 1);
        $lifecycle = app(CampaignLifecycle::class);
        $lifecycle->activate($campaign);
        $lock = Cache::lock(CampaignLifecycle::lockName($this->target), 2000);
        $this->assertTrue($lock->get());
        try {
            $lifecycle->tick();
            $this->assertNull($campaign->fresh()->lifecycle_error);
            $this->assertNull(CampaignDeviceFile::sole()->last_error);
            Queue::assertPushed(ProcessCampaignDeviceJob::class);
        } finally {
            $lock->release();
        }
        $lifecycle->tick();
        $this->assertSame('active', $campaign->fresh()->status);
        $this->assertNull($campaign->fresh()->lifecycle_error);
    }

    public function test_failure_before_selecting_a_file_is_safe_and_visible_on_the_device(): void
    {
        $campaign = $this->campaign([], 1);
        $transport = $this->mock(CampaignDeviceTransport::class);
        $transport->shouldReceive('power')->once()->andThrow(new \RuntimeException('secret=https://host/token?password=secret'));
        $lifecycle = app(CampaignLifecycle::class);
        $lifecycle->activate($campaign);
        $previous = $this->campaign(['status' => 'finished'], 1);
        CampaignDevicePlayback::create(['target_key' => $this->target, 'campaign_id' => $previous->id]);
        $logger = \Mockery::mock();
        $logger->shouldReceive('error')->once()->withArgs(function ($message, $context) {
            return str_contains($message, 'Falló') && $context['target'] === $this->target
                && $context['exception_type'] === \RuntimeException::class
                && ! str_contains(json_encode($context), 'secret');
        });
        Log::shouldReceive('channel')->once()->with('diagnostics')->andReturn($logger);
        $lifecycle->processDevice($this->target);
        $this->assertNull(CampaignDeviceFile::sole()->last_error);
        $this->assertSame('No se pudo procesar el dispositivo. Revisa los registros del servidor.', CampaignDevicePlayback::sole()->last_error);
    }

    public function test_failed_attempt_keeps_error_until_a_later_confirmed_publication(): void
    {
        $campaign = $this->campaign([], 1);
        $transport = $this->mock(CampaignDeviceTransport::class);
        $transport->shouldReceive('install')->once()->andThrow(new \RuntimeException('Se necesita un reporte reciente del contenido del equipo 42.'));
        $transport->shouldReceive('install')->once()->andReturnUsing(fn ($file) => $file->update(['state' => 'ready', 'confirmed_at' => now(), 'last_error' => null]));
        $lifecycle = app(CampaignLifecycle::class);
        $lifecycle->activate($campaign);
        $lifecycle->processDevice($this->target);
        $this->assertNotNull(CampaignDevicePlayback::sole()->last_error);
        $lifecycle->processDevice($this->target);
        $this->assertNull(CampaignDevicePlayback::sole()->last_error);
        $this->assertNull(CampaignDeviceFile::sole()->last_error);
        $this->assertSame('ready', CampaignDeviceFile::sole()->state);
    }

    public function test_worker_start_is_observed_without_assuming_publication_or_a_running_worker(): void
    {
        $lifecycle = \Mockery::mock(CampaignLifecycle::class);
        $lifecycle->shouldReceive('processDevice')->once()->with($this->target);
        $activity = app(CampaignActivity::class);
        $this->assertNull($activity->lastDeviceStart($this->target));
        (new ProcessCampaignDeviceJob($this->target))->handle($lifecycle);
        $this->assertSame(now()->toIso8601String(), $activity->lastDeviceStart($this->target));
        $this->travel(25)->hours();
        $this->assertNull($activity->lastDeviceStart($this->target));
    }

    public function test_scheduler_failure_is_logged_at_most_once_per_minute(): void
    {
        $logger = \Mockery::mock();
        $logger->shouldReceive('error')->twice()->with('Falló el procesador de campañas; revisa el scheduler y la configuración.');
        Log::shouldReceive('channel')->twice()->with('diagnostics')->andReturn($logger);
        $activity = app(CampaignActivity::class);
        $activity->schedulerFailed();
        $activity->schedulerFailed();
        $this->travel(61)->seconds();
        $activity->schedulerFailed();
    }

    public function test_physical_alias_in_another_ledger_protects_uploaded_filename(): void
    {
        $first = $this->campaign([], 1);
        $second = $this->campaign([], 1);
        $transport = $this->mock(CampaignDeviceTransport::class);
        $transport->shouldNotReceive('remove');
        $transport->shouldReceive('select')->once();
        $lifecycle = app(CampaignLifecycle::class);
        $lifecycle->activate($first);
        $lifecycle->activate($second);
        $this->ready($first);
        $this->ready($second);
        $physical = $first->deliveries->first()->file->filename;
        $second->deliveries->first()->file->update(['filename' => $physical]);
        $lifecycle->finish($first);
        $lifecycle->processDevice($this->target);
        $this->assertSame('ready', $first->deliveries->first()->file->fresh()->state);
    }

    public function test_finished_installing_campaign_cancels_z2_delivery_instead_of_reasserting_play(): void
    {
        $campaign = $this->campaign([], 1);
        $transport = $this->mock(CampaignDeviceTransport::class);
        $transport->shouldNotReceive('confirmInstallation');
        $transport->shouldReceive('remove')->once()->andReturnUsing(fn ($file) => $file->update(['state' => 'removing']));
        $lifecycle = app(CampaignLifecycle::class);
        $lifecycle->activate($campaign);
        $campaign->deliveries->first()->file->update(['state' => 'installing', 'owned' => true]);
        $lifecycle->finish($campaign);
        $lifecycle->processDevice($this->target);
    }

    public function test_invalid_second_source_leaves_no_partial_snapshot(): void
    {
        $campaign = $this->campaign();
        $ids = $campaign->media()->pluck('media.id')->all();
        $this->mock(MediaPreviewSource::class)->shouldReceive('resolve')->andReturnUsing(fn ($media) => $media->id === $ids[0] ? ['kind' => 'private_cloud', 'path' => '/fileDownload/Videos/first.mp4'] : null);
        try {
            app(CampaignLifecycle::class)->activate($campaign);
            $this->fail();
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('media_ids', $e->errors());
        }
        $this->assertDatabaseCount('campaign_device_files', 0);
        $this->assertDatabaseCount('campaign_deliveries', 0);
        $this->assertSame('draft', $campaign->fresh()->status);
    }

    public function test_retired_target_is_not_republished_when_snapshot_is_reconciled(): void
    {
        $device = Device::factory()->create(['mac_address' => 'AABBCCDDEEFF']);
        DB::table('device_retirement_exclusions')->insert(['device_id' => $device->id, 'scope' => 'campaign', 'resource_id' => '1']);
        $campaign = $this->campaign(['id' => 1, 'target_devices' => [$this->target, 'wl35:other']]);
        app(CampaignLifecycle::class)->activate($campaign);
        $this->assertSame(['wl35:other'], CampaignDeviceFile::pluck('target_key')->unique()->values()->all());
    }

    public function test_scheduled_error_survives_before_any_delivery_exists(): void
    {
        $campaign = $this->campaign(['status' => 'scheduled', 'start_date' => null]);
        $campaign->media()->first()->update(['duration' => 0]);
        app(CampaignLifecycle::class)->tick();
        $this->assertSame('pending', $campaign->fresh()->duration_preparation_state);
        $this->assertDatabaseCount('campaign_deliveries', 0);
    }

    public function test_sync_queue_is_rejected_before_device_work(): void
    {
        config(['queue.default' => 'sync']);
        $campaign = $this->campaign();
        $this->expectException(\RuntimeException::class);
        app(CampaignLifecycle::class)->activate($campaign);
    }

    public function test_handoff_stops_expired_playback_before_pending_campaign_is_ready(): void
    {
        $first = $this->campaign(['priority' => 10], 1);
        $next = $this->campaign(['priority' => 1], 1);
        $transport = $this->mock(CampaignDeviceTransport::class);
        $transport->shouldReceive('power')->once()->with($this->target, false)->ordered();
        $transport->shouldReceive('remove')->once()->andReturnUsing(fn ($file) => $file->update(['state' => 'removed']))->ordered();
        $lifecycle = app(CampaignLifecycle::class);
        $lifecycle->activate($first);
        $lifecycle->activate($next);
        $this->ready($first);
        CampaignDevicePlayback::create(['target_key' => $this->target, 'campaign_id' => $first->id, 'delivery_id' => $first->deliveries->first()->id, 'due_at' => now()->addMinute()]);
        $lifecycle->finish($first);
        $lifecycle->processDevice($this->target);
        $this->assertTrue(CampaignDevicePlayback::sole()->powered_off);
        $this->assertNull(CampaignDevicePlayback::sole()->campaign_id);
    }

    public function test_future_campaign_does_not_hold_expired_campaign_file_indefinitely(): void
    {
        $first = $this->campaign([], 1);
        $future = $this->campaign(['start_date' => now()->addDays(2), 'end_date' => now()->addDays(3)], 1);
        $future->media()->first()->update(['file_path' => 'future/'.basename($first->media()->first()->file_path)]);
        $transport = $this->mock(CampaignDeviceTransport::class);
        $transport->shouldReceive('remove')->once()->andReturnUsing(fn ($file) => $file->update(['state' => 'removed']));
        $lifecycle = app(CampaignLifecycle::class);
        $lifecycle->activate($first);
        $lifecycle->activate($future);
        $this->ready($first);
        $lifecycle->finish($first);
        $lifecycle->processDevice($this->target);
        $this->assertSame('removed', CampaignDeviceFile::sole()->state);
    }
}
