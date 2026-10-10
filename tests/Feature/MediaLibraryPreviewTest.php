<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\User;
use App\Services\Fleet\DevicePreviewService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Js;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MediaLibraryPreviewTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aBd0AAAAASUVORK5CYII=';

    protected function setUp(): void
    {
        parent::setUp();
        config(['privatecloud.base_url' => 'http://cloud.test', 'privatecloud.token' => 'test-token',
            'unifiedfleet.base_url' => 'http://gateway.test', 'unifiedfleet.token' => 'test-token', 'unifiedfleet.fallback_base_url' => '']);
        Http::preventStrayRequests();
        Storage::fake('public');
        Storage::fake('local');
    }

    private function media(string $path, string $mime = 'video/mp4', ?int $duration = null): Media
    {
        return Media::create([
            'name' => "Video 'especial'", 'original_name' => basename($path), 'file_path' => $path,
            'mime_type' => $mime, 'size' => 100, 'duration' => $duration,
        ]);
    }

    public function test_library_grid_list_and_detail_render_same_origin_preview_and_duration(): void
    {
        $video = $this->media('campaign.mp4', duration: 3661);
        $image = $this->media('media/photo.png', 'image/png');
        Http::fake(['cloud.test/api/media' => Http::response(['result' => 0, 'media' => [
            ['filename' => 'campaign.mp4', 'size' => 100],
        ]])]);
        $this->actingAs(User::factory()->admin()->create());

        $index = $this->get(route('media.index'))->assertOk()->assertSee('mediaPreview(')
            ->assertSee('preload="metadata"', false)->assertSee('1:01:01')->assertSee('mediaDurations.label');
        foreach ([$video, $image] as $media) {
            $index->assertSee(Js::from(route('media.content', $media))->toHtml(), false);
        }
        $this->assertSame(4, substr_count($index->getContent(), 'x-data="mediaPreview('));
        $this->get(route('media.show', $video))->assertOk()->assertSee('controls', false)
            ->assertSee(Js::from(route('media.content', $video))->toHtml(), false)->assertSee('1:01:01');
        $this->get(route('media.show', $image))->assertOk()->assertSee('alt="Video', false);
        Http::assertSentCount(1);
    }

    public function test_safe_local_raster_is_authenticated_and_uses_real_mime_type(): void
    {
        $media = $this->media('media/picture.jpg', 'image/jpeg');
        Storage::disk('public')->put($media->file_path, base64_decode(self::PNG));
        $this->getJson(route('media.content', $media))->assertUnauthorized();
        $this->actingAs(User::factory()->admin()->create())->get(route('media.content', $media))
            ->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
        Http::assertNothingSent();
    }

    public function test_historical_private_local_video_is_served_without_remote_request(): void
    {
        $media = $this->media('media/legacy.mp4');
        Storage::disk('local')->put($media->file_path, '0123456789');
        $response = $this->actingAs(User::factory()->admin()->create())->withHeader('Range', 'bytes=0-2')
            ->get(route('media.content', $media))->assertStatus(206);
        ob_start();
        $response->baseResponse->sendContent();
        $this->assertSame('012', ob_get_clean());
        Http::assertNothingSent();
    }

    public function test_detail_can_stream_identified_legacy_cloud_source_without_prior_sync(): void
    {
        $media = $this->media('http://cloud.test/fileDownload/Videos/my%20video.mp4');
        Http::fake(['cloud.test/fileDownload/Videos/my%20video.mp4' => Http::response('bytes', 200)]);
        $this->actingAs(User::factory()->admin()->create())->get(route('media.show', $media))
            ->assertOk()->assertSee(Js::from(route('media.content', $media))->toHtml(), false)
            ->assertDontSee('http://cloud.test/fileDownload', false);
        Http::assertNothingSent();
        $this->get(route('media.content', $media))->assertOk()->assertStreamedContent('bytes');
        Http::assertSent(fn (Request $request) => $request->url() === 'http://cloud.test/fileDownload/Videos/my%20video.mp4');
    }

    public static function unsafeImages(): array
    {
        return [
            'html disguised as raster' => ['image/png', '<html><script>alert(1)</script></html>'],
            'svg disguised as raster' => ['image/png', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'],
            'svg record' => ['image/svg+xml', '<svg xmlns="http://www.w3.org/2000/svg"/>'],
        ];
    }

    #[DataProvider('unsafeImages')]
    public function test_non_raster_content_is_never_served_as_an_image(string $mime, string $body): void
    {
        $media = $this->media('media/unsafe.png', $mime);
        Storage::disk('public')->put($media->file_path, $body);

        $this->actingAs(User::factory()->admin()->create())->get(route('media.content', $media))->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_external_or_traversing_legacy_urls_are_not_proxied(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        foreach (['http://cloud.test.evil/fileDownload/Videos/x.mp4',
            'http://cloud.test/fileDownload/Videos/%2e%2e%2fx.mp4',
            'http://cloud.test/fileDownload/Videos/x.mp4?source=external', '../secret.mp4'] as $path) {
            $this->get(route('media.content', $this->media($path)))->assertNotFound();
        }
        Http::assertNothingSent();
    }

    public function test_device_playback_preview_stays_video_only(): void
    {
        $image = $this->media('media/picture.png', 'image/png');
        Storage::disk('public')->put($image->file_path, base64_decode(self::PNG));
        Http::fake(['gateway.test/api/fleet' => Http::response(['devices' => [[
            'type' => 'z2', 'id' => 'fan-a', 'online' => true, 'power' => true,
            'current_video' => $image->file_path,
        ]]])]);

        $device = app(DevicePreviewService::class)->snapshot()[0];
        $this->assertSame('missing_file', $device['status']);
        $this->assertNull($device['url']);
    }
}
