<?php

namespace Tests\Feature;

use App\Jobs\PrepareCampaignDurations;
use App\Models\Campaign;
use App\Models\Media;
use App\Models\Schedule;
use App\Models\User;
use App\Services\Campaigns\CampaignLifecycle;
use App\Services\CampaignTargetCatalog;
use App\Services\Fleet\MediaPreviewSource;
use App\Services\Fleet\MediaSourceMaterializer;
use App\Services\Fleet\VideoDurationProbe;
use App\Services\PrivateCloud\PrivateCloudClient;
use App\Services\Z2\Z2VideoService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CampaignDurationPreparationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Queue::fake();
        Storage::fake('local');
        config(['cache.default' => 'database', 'queue.default' => 'database', 'queue.connections.database.retry_after' => 2000]);
        $this->mock(CampaignTargetCatalog::class)->shouldReceive('validatedKeys')->andReturnUsing(fn ($keys) => $keys);
        app(CampaignTargetCatalog::class)->shouldReceive('all')->andReturn(collect());
        app(CampaignTargetCatalog::class)->shouldReceive('forCampaign')->andReturn(collect());
        $this->mock(MediaPreviewSource::class)->shouldReceive('resolve')->andReturnUsing(fn ($media) => ['kind' => 'private_cloud', 'path' => '/fileDownload/Videos/'.basename($media->file_path)]);
    }

    private function campaign(?Media $media = null): Campaign
    {
        $campaign = Campaign::factory()->draft()->create(['target_devices' => ['z2:AABBCCDDEEFF'], 'start_date' => now()->subMinute(), 'end_date' => now()->addHour()]);
        $campaign->media()->attach($media ?? Media::factory()->video()->create(['duration' => null]));

        return $campaign;
    }

    private function job(Campaign $campaign): PrepareCampaignDurations
    {
        $campaign = $campaign->fresh();

        return new PrepareCampaignDurations($campaign->id, $campaign->duration_preparation_token, app(CampaignLifecycle::class)->durationFingerprint($campaign));
    }

    public function test_activation_queues_preparation_and_activates_after_real_duration(): void
    {
        $campaign = $this->campaign();
        $lifecycle = app(CampaignLifecycle::class);
        $this->assertFalse($lifecycle->activate($campaign));
        $this->assertFalse($lifecycle->activate($campaign));
        Queue::assertPushed(PrepareCampaignDurations::class, 1);
        $this->assertSame('draft', $campaign->fresh()->status);
        $this->assertSame('pending', $campaign->fresh()->duration_preparation_state);
        $probe = $this->mock(VideoDurationProbe::class);
        $probe->shouldReceive('recover')->once()->andReturn(31);
        $this->job($campaign)->handle($probe, $lifecycle);
        $this->assertSame('active', $campaign->fresh()->status);
        $this->assertNull($campaign->fresh()->duration_preparation_state);
        $this->assertSame(31, $campaign->media()->first()->duration);
        $this->assertSame(31, $campaign->deliveries()->first()->duration);
        Http::assertNothingSent();
    }

    public function test_web_reports_preparation_not_activation(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $campaign = $this->campaign();
        $this->post(route('campaigns.activate', $campaign))->assertSessionHas('success', 'Preparando la duración de los videos. La campaña se activará cuando estén listos.');
        $this->get(route('campaigns.index'))->assertSee('Preparando duración de videos');
        $this->get(route('campaigns.show', $campaign))->assertSee('Preparando la duración de los videos');
        $this->assertSame('draft', $campaign->fresh()->status);
    }

    public function test_changed_paused_expired_or_deleted_campaign_does_not_reactivate(): void
    {
        $lifecycle = app(CampaignLifecycle::class);
        $probe = $this->mock(VideoDurationProbe::class);
        $probe->shouldNotReceive('recover');
        foreach (['edit', 'pause', 'expiry', 'delete'] as $kind) {
            $campaign = $this->campaign();
            $lifecycle->activate($campaign);
            $job = $this->job($campaign);
            match ($kind) {
                'edit' => $campaign->update(['name' => 'Changed']),
                'pause' => $campaign->update(['status' => 'paused']),
                'expiry' => $campaign->update(['end_date' => now()->subMinute()]),
                'delete' => $campaign->delete(),
            };
            $job->handle($probe, $lifecycle);
            $this->assertNotSame('active', $campaign->fresh()?->status);
        }
    }

    public function test_shared_media_is_probed_once_and_both_campaigns_can_activate(): void
    {
        $media = Media::factory()->video()->create(['duration' => null]);
        $a = $this->campaign($media);
        $b = $this->campaign($media);
        $lifecycle = app(CampaignLifecycle::class);
        $lifecycle->activate($a);
        $lifecycle->activate($b);
        $jobA = $this->job($a);
        $jobB = $this->job($b);
        $probe = $this->mock(VideoDurationProbe::class);
        $probe->shouldReceive('recover')->once()->andReturn(15);
        $jobA->handle($probe, $lifecycle);
        $jobB->handle($probe, $lifecycle);
        $this->assertSame('active', $a->fresh()->status);
        $this->assertSame('active', $b->fresh()->status);
    }

    public function test_failure_is_visible_and_explicit_retry_can_start_new_job(): void
    {
        $campaign = $this->campaign();
        $lifecycle = app(CampaignLifecycle::class);
        $lifecycle->activate($campaign);
        $oldToken = $campaign->fresh()->duration_preparation_token;
        $probe = $this->mock(VideoDurationProbe::class);
        $probe->shouldReceive('recover')->once()->andReturnNull();
        $this->job($campaign)->handle($probe, $lifecycle);
        $this->assertSame('failed', $campaign->fresh()->duration_preparation_state);
        $this->actingAs(User::factory()->admin()->create());
        $this->get(route('campaigns.index'))->assertSee('Reintentar preparación');
        $this->assertStringContainsString('No se pudo obtener la duración', $campaign->fresh()->lifecycle_error);
        $this->assertSame('draft', $campaign->fresh()->status);
        $this->assertFalse($lifecycle->activate($campaign));
        $this->assertNotSame($oldToken, $campaign->fresh()->duration_preparation_token);
        Queue::assertPushed(PrepareCampaignDurations::class, 2);
    }

    public function test_probe_uses_real_result_rounds_up_and_cleans_temp_even_on_failure(): void
    {
        $media = Media::factory()->video()->create(['duration' => null]);
        $materializer = $this->mock(MediaSourceMaterializer::class);
        $materializer->shouldReceive('materialize')->twice()->andReturnUsing(function ($media, $path) {
            Storage::disk('local')->put($path, 'fixture');

            return 7;
        });
        Process::fake(['*' => Process::sequence()->push('{"streams":[{"duration":"0.4"}]}')->push('', '', 1)]);
        $probe = app(VideoDurationProbe::class);
        $this->assertSame(1, $probe->recover($media));
        $this->assertNull($probe->recover($media));
        $this->assertSame([], Storage::disk('local')->allFiles('media-duration'));
        Http::assertNothingSent();
    }

    public function test_new_cloud_upload_stores_probed_duration_and_failure_does_not_block_upload(): void
    {
        $client = $this->mock(PrivateCloudClient::class);
        $client->shouldReceive('postFile')->twice()->andReturn(
            ['result' => 0, 'filename' => 'known.mp4', 'size' => 100],
            ['result' => 0, 'filename' => 'unknown.mp4', 'size' => 100],
        );
        $probe = $this->mock(VideoDurationProbe::class);
        $probe->shouldReceive('file')->twice()->andReturn(29, null);
        $service = new Z2VideoService($client);
        $known = $service->uploadVideo('temp.mp4', 'known.mp4');
        $unknown = $service->uploadVideo('temp.mp4', 'unknown.mp4');
        $this->assertSame(29, $known->fresh()->duration);
        $this->assertNull($unknown->fresh()->duration);
    }

    public function test_web_fallback_upload_stores_probed_duration(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->admin()->create());
        $this->mock(Z2VideoService::class)->shouldReceive('uploadVideo')->once()->andReturnNull();
        $this->mock(VideoDurationProbe::class)->shouldReceive('file')->once()->andReturn(17);
        $this->post(route('media.store'), ['file' => UploadedFile::fake()->create('movie.mp4', 10, 'video/mp4')])->assertRedirect(route('media.index'));
        $this->assertSame(17, Media::where('original_name', 'movie.mp4')->sole()->duration);
        Http::assertNothingSent();
    }

    public function test_existing_cloud_source_is_materialized_using_bounded_authenticated_client(): void
    {
        config(['privatecloud.base_url' => 'http://private.invalid', 'privatecloud.token' => 'fixture-token']);
        Http::fake(function (Request $request, array $options) {
            $this->assertStringStartsWith('http://private.invalid/fileDownload/Videos/', $request->url());
            $this->assertTrue($request->hasHeader('Authorization', 'Bearer fixture-token'));
            $this->assertFalse($options['allow_redirects']);
            $this->assertArrayHasKey('progress', $options);
            file_put_contents($options['sink'], 'video-data');

            return Http::response('video-data', 200);
        });
        Process::fake(['*' => Process::result('{"format":{"duration":"12.1"}}')]);
        $media = Media::factory()->video()->create(['duration' => null]);
        $this->assertSame(13, app(VideoDurationProbe::class)->recover($media));
        $this->assertSame([], Storage::disk('local')->allFiles('media-duration'));
        Http::assertSentCount(1);
    }

    public function test_changed_video_source_is_not_assigned_old_duration(): void
    {
        $campaign = $this->campaign();
        $media = $campaign->media()->first();
        $lifecycle = app(CampaignLifecycle::class);
        $lifecycle->activate($campaign);
        $probe = $this->mock(VideoDurationProbe::class);
        $probe->shouldReceive('recover')->once()->andReturnUsing(function () use ($media) {
            $media->update(['file_path' => 'replacement.mp4']);

            return 50;
        });
        $this->job($campaign)->handle($probe, $lifecycle);
        $this->assertNull($media->fresh()->duration);
        $this->assertSame('failed', $campaign->fresh()->duration_preparation_state);
        $this->assertSame('draft', $campaign->fresh()->status);
    }

    public function test_campaign_edit_during_probe_never_activates_old_intent(): void
    {
        $campaign = $this->campaign();
        $lifecycle = app(CampaignLifecycle::class);
        $lifecycle->activate($campaign);
        $job = $this->job($campaign);
        $probe = $this->mock(VideoDurationProbe::class);
        $probe->shouldReceive('recover')->once()->andReturnUsing(function () use ($campaign) {
            $campaign->update(['target_devices' => ['z2:DIFFERENT']]);

            return 10;
        });
        $job->handle($probe, $lifecycle);
        $this->assertSame('draft', $campaign->fresh()->status);
        $this->assertSame('failed', $campaign->fresh()->duration_preparation_state);
        $this->assertDatabaseCount('campaign_deliveries', 0);
    }

    public function test_scheduled_campaign_does_not_duplicate_jobs_while_preparing_or_retry_failed_automatically(): void
    {
        $campaign = $this->campaign();
        $campaign->update(['status' => 'scheduled']);
        $lifecycle = app(CampaignLifecycle::class);
        $lifecycle->tick();
        $lifecycle->tick();
        Queue::assertPushed(PrepareCampaignDurations::class, 1);
        $probe = $this->mock(VideoDurationProbe::class);
        $probe->shouldReceive('recover')->once()->andReturnNull();
        $this->job($campaign)->handle($probe, $lifecycle);
        $lifecycle->tick();
        Queue::assertPushed(PrepareCampaignDurations::class, 1);
        $this->assertSame('failed', $campaign->fresh()->duration_preparation_state);
        $this->actingAs(User::factory()->admin()->create());
        $this->get(route('campaigns.index'))->assertSee('Reintentar preparación');
    }

    public function test_schedule_stays_pending_until_preparation_succeeds(): void
    {
        $campaign = $this->campaign();
        $schedule = Schedule::factory()->pending()->create(['type' => 'activate_campaign', 'campaign_id' => $campaign->id,
            'device_id' => null, 'group_id' => null, 'scheduled_at' => now()->subMinute()]);
        $this->artisan('schedules:process')->assertSuccessful();
        $this->assertSame('pending', $schedule->fresh()->status);
        $probe = $this->mock(VideoDurationProbe::class);
        $probe->shouldReceive('recover')->once()->andReturn(20);
        $this->job($campaign)->handle($probe, app(CampaignLifecycle::class));
        $this->artisan('schedules:process')->assertSuccessful();
        $this->assertSame('executed', $schedule->fresh()->status);
        $this->assertSame('active', $campaign->fresh()->status);
    }
}
