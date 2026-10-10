<?php

namespace Tests\Feature;

use App\Jobs\PlayFleetMediaJob;
use App\Models\Device;
use App\Models\FleetUpload;
use App\Models\Media;
use App\Models\User;
use App\Services\Fleet\MediaSourceMaterializer;
use App\Services\Z2\Z2DeviceService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DevicePlaybackTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['privatecloud.base_url' => 'http://cloud.test', 'privatecloud.token' => 'test-token',
            'unifiedfleet.base_url' => 'http://gateway.test', 'unifiedfleet.fallback_base_url' => '',
            'unifiedfleet.token' => 'test-token', 'queue.default' => 'database']);
        Http::preventStrayRequests();
        Storage::fake('public');
        Storage::fake('local');
    }

    private function media(string $path = 'campaign.mp4'): Media
    {
        return Media::forceCreate(['id' => 58702, 'name' => 'Campaña 28 segundos', 'original_name' => 'campaign.mp4',
            'mime_type' => 'video/mp4', 'file_path' => $path, 'duration' => 28, 'size' => 128]);
    }

    private function operation(Media $media): FleetUpload
    {
        return FleetUpload::create(['user_id' => User::factory()->admin()->create()->id, 'source_media_id' => $media->id,
            'original_name' => 'campaign.mp4', 'file_path' => 'fleet-uploads/test.mp4', 'targets' => ['z2:AABB'],
            'status' => 'queued', 'phase' => 'queued', 'progress' => 10, 'play_after_upload' => true]);
    }

    public function test_three_screens_select_the_same_library_id_and_preserve_stored_controls(): void
    {
        $media = $this->media();
        $device = Device::factory()->create(['mac_address' => 'AA:BB']);
        Http::fake(['cloud.test/api/devices*' => Http::response(['result' => 0, 'devices' => [], 'device' => ['playlist' => []]]),
            'gateway.test/api/fleet' => Http::response(['devices' => [], 'media' => []])]);
        $this->actingAs(User::factory()->admin()->create());
        foreach ([route('devices.show', $device), route('devices.index'), route('instant-play.index')] as $url) {
            $this->get($url)->assertOk()->assertSee(route('instant-play.media'), false)
                ->assertSee('value="58702"', false)->assertSee($media->name);
        }
        $this->get(route('devices.show', $device))->assertSee('value="z2:AABB"', false);
        $this->get(route('devices.index'))->assertSee('Reproducir contenido ya almacenado')
            ->assertSee('name="wl35_video_index"', false)->assertSee('name="z2_filename"', false);
    }

    public function test_library_dispatch_keeps_media_identity_targets_and_duration(): void
    {
        Bus::fake([PlayFleetMediaJob::class]);
        $media = $this->media();
        $this->actingAs(User::factory()->admin()->create())->postJson(route('instant-play.media'), [
            'media_id' => $media->id, 'targets' => ['z2:AABB', 'wl35:fan-a'],
        ])->assertStatus(202)->assertJsonPath('success', true);
        $upload = FleetUpload::firstOrFail();
        $this->assertSame($media->id, $upload->source_media_id);
        $this->assertSame(['z2:AABB', 'wl35:fan-a'], $upload->targets);
        $this->assertSame(28, $media->fresh()->duration);
        Bus::assertDispatched(PlayFleetMediaJob::class, fn ($job) => $job->fleetUploadId === $upload->id);
        Http::assertNothingSent();
    }

    #[DataProvider('cloudPaths')]
    public function test_cloud_and_legacy_sources_send_the_same_filename_without_reupload(string $path, string $filename): void
    {
        $media = $this->media($path);
        Http::fake(['gateway.test/api/health' => Http::response(['ok' => true]),
            'gateway.test/api/fleet/commands' => Http::response(['results' => [['key' => 'z2:AABB', 'success' => true]]]),
            'cloud.test/api/devices/AABB/play' => Http::response(['result' => 0])]);
        $upload = $this->operation($media);
        app()->call([new PlayFleetMediaJob($upload->id), 'handle']);
        $this->assertTrue(app(Z2DeviceService::class)->changeVideo('AA:BB', $path));
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/api/fleet/commands')
            && $request['z2_filename'] === $filename && $request['targets'] === ['z2:AABB']);
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/AABB/play') && $request['filename'] === $filename);
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/upload'));
        $this->assertSame('completed', $upload->fresh()->status);
        $this->assertSame(28, $media->fresh()->duration);
    }

    public static function cloudPaths(): array
    {
        return [['campaign.mp4', 'campaign.mp4'], ['http://cloud.test/fileDownload/Videos/campaign%20long.mp4', 'campaign long.mp4']];
    }

    #[DataProvider('localDisks')]
    public function test_local_materialization_and_direct_upload_keep_complete_selected_bytes(string $disk): void
    {
        $media = $this->media('media/campaign.mp4');
        $bytes = str_repeat('selected-video-28-seconds-', 128);
        Storage::disk($disk)->put($media->file_path, $bytes);
        $size = app(MediaSourceMaterializer::class)->materialize($media, 'fleet-uploads/copy.mp4');
        $this->assertSame(strlen($bytes), $size);
        $this->assertSame($bytes, Storage::disk('local')->get('fleet-uploads/copy.mp4'));
        Http::fake(function (Request $request) use ($bytes) {
            if (str_ends_with($request->url(), '/AABB/upload')) {
                $this->assertStringContainsString($bytes, $request->body());

                return Http::response(['result' => 0, 'filename' => 'new-upload.mp4']);
            }
            $this->assertSame('new-upload.mp4', $request['filename']);

            return Http::response(['result' => 0]);
        });
        $this->assertTrue(app(Z2DeviceService::class)->changeVideo('AA:BB', $media->file_path));
        Storage::disk($disk)->assertExists($media->file_path);
        $this->assertSame('media/campaign.mp4', $media->fresh()->file_path);
        $this->assertSame(28, $media->fresh()->duration);
    }

    public static function localDisks(): array
    {
        return [['public'], ['local']];
    }

    #[DataProvider('gatewayUploadOutcomes')]
    public function test_worker_distributes_complete_local_bytes_or_reports_rejected_upload(bool $accepted): void
    {
        $media = $this->media('media/campaign.mp4');
        $bytes = str_repeat('complete-selected-28-seconds-', 128);
        $this->assertTrue(Storage::disk('local')->put($media->file_path, $bytes));
        Http::fake(function (Request $request) use ($bytes, $accepted) {
            if (str_ends_with($request->url(), '/api/health')) {
                return Http::response(['ok' => true]);
            }
            if (str_ends_with($request->url(), '/api/fleet/uploads')) {
                $this->assertSame($bytes, $request->body());
                $this->assertSame((string) strlen($bytes), $request->header('Content-Length')[0]);

                return $accepted ? Http::response(['upload_id' => 'gateway-job'], 201)
                    : Http::response(['error' => 'rejected'], 500);
            }
            $this->assertStringEndsWith('/gateway-job/distribute', $request->url());
            $this->assertTrue($request['play_after_upload']);
            $this->assertSame(['z2:AABB'], $request['targets']);

            return Http::response(['results' => [['key' => 'z2:AABB', 'type' => 'z2', 'success' => true]]]);
        });
        $upload = $this->operation($media);
        app()->call([new PlayFleetMediaJob($upload->id), 'handle']);
        $this->assertSame('completed', $upload->fresh()->status);
        $this->assertSame($accepted ? 0 : 1, $upload->fresh()->result['failed']);
        Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/api/fleet/commands'));
        if (! $accepted) {
            Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/distribute'));
        }
        Storage::disk('local')->assertExists($media->file_path);
        Storage::disk('local')->assertMissing($upload->file_path);
        $this->assertSame(28, $media->fresh()->duration);
    }

    public static function gatewayUploadOutcomes(): array
    {
        return [[true], [false]];
    }

    public function test_missing_local_source_never_plays_a_cloud_basename(): void
    {
        $media = $this->media('media/campaign.mp4');
        try {
            app(Z2DeviceService::class)->changeVideo('AA:BB', $media->file_path);
            $this->fail('Missing source must fail before sending play.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('archivo de video', $exception->getMessage());
        }
        Http::assertNothingSent();
    }

    #[DataProvider('rejectedUploads')]
    public function test_rejected_or_invalid_upload_never_plays_old_basename(array $response): void
    {
        $media = $this->media('media/campaign.mp4');
        $this->assertTrue(Storage::disk('public')->put($media->file_path, 'selected-video'));
        Http::fake(['cloud.test/api/devices/AABB/upload' => Http::response($response)]);
        try {
            app(Z2DeviceService::class)->changeVideo('AA:BB', $media->file_path);
            $this->fail('Rejected upload must not fall back to a previous cloud file.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('No se envió', $exception->getMessage());
        }
        Http::assertSentCount(1);
        Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/play'));
        Storage::disk('public')->assertExists($media->file_path);
    }

    public static function rejectedUploads(): array
    {
        return [[['result' => -1]], [['result' => 0]], [['result' => 0, 'filename' => '../wrong.mp4']]];
    }

    public function test_worker_does_not_dispatch_missing_or_external_media(): void
    {
        $media = $this->media('https://untrusted.test/campaign.mp4');
        $upload = $this->operation($media);
        try {
            app()->call([new PlayFleetMediaJob($upload->id), 'handle']);
            $this->fail('An unavailable source must fail the worker.');
        } catch (\RuntimeException) {
            $this->assertSame('failed', $upload->fresh()->status);
        }
        Http::assertNothingSent();
    }

    public function test_library_route_rejects_images_deleted_media_and_unavailable_queue(): void
    {
        $media = $this->media();
        $user = User::factory()->admin()->create();
        $payload = ['media_id' => $media->id, 'targets' => ['z2:AABB']];
        $media->update(['mime_type' => 'image/png']);
        $this->actingAs($user)->postJson(route('instant-play.media'), $payload)->assertUnprocessable();
        $media->update(['mime_type' => 'video/mp4']);
        config(['queue.default' => 'sync']);
        $this->postJson(route('instant-play.media'), $payload)->assertStatus(500)->assertJsonPath('success', false);
        $this->assertSame(0, FleetUpload::count());
        $media->delete();
        $this->postJson(route('instant-play.media'), $payload)->assertUnprocessable();
        Http::assertNothingSent();
    }
}
