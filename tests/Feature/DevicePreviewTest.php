<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Media;
use App\Models\User;
use App\Models\Wl35DeviceMedia;
use App\Models\Wl35DeviceProfile;
use App\Services\Fleet\DevicePreviewService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Js;
use Tests\TestCase;

class DevicePreviewTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'unifiedfleet.base_url' => 'http://gateway.test', 'unifiedfleet.fallback_base_url' => '',
            'unifiedfleet.token' => 'gateway-test-token', 'privatecloud.base_url' => 'http://cloud.test',
            'privatecloud.token' => 'cloud-test-token',
        ]);
        Http::preventStrayRequests();
        Storage::fake('public');
    }

    private function media(string $path, ?string $original = null): Media
    {
        return Media::create([
            'name' => 'Contenido '.$path, 'original_name' => $original ?? basename($path),
            'file_path' => $path, 'mime_type' => 'video/mp4', 'size' => 10,
        ]);
    }

    private function telemetry(array $devices): void
    {
        Http::fake([
            'gateway.test/api/fleet' => Http::response(['devices' => $devices]),
            'cloud.test/api/devices' => Http::response(['devices' => []]),
        ]);
    }

    private function active(string $type, string $id, mixed $current): array
    {
        return ['type' => $type, 'id' => $id, 'online' => true, 'power' => true,
            'connected' => true, 'session_ready' => true, 'current_video' => $current, 'video_count' => 3];
    }

    public function test_z2_uses_exact_reported_file_and_never_the_desired_video(): void
    {
        $current = $this->media('campaign.mp4');
        $this->media('next.mp4');
        $device = Device::factory()->create(['mac_address' => 'AA:BB:CC:DD:EE:FF']);
        $this->telemetry([array_merge($this->active('z2', 'AABBCCDDEEFF', 'campaign.mp4'), ['desired_video' => 'next.mp4'])]);
        $this->actingAs(User::factory()->admin()->create())->getJson(route('devices.previews'))
            ->assertOk()->assertJsonPath('devices.0.status', 'ready')
            ->assertJsonPath('devices.0.url', route('media.content', $current))
            ->assertJsonPath('devices.0.detail_url', route('devices.show', $device))
            ->assertDontSee('cloud-test-token')->assertDontSee('gateway-test-token');
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request->method() === 'GET');
    }

    public function test_wl35_mapping_is_specific_to_the_device_and_valid_reported_index(): void
    {
        $media = $this->media('campaign.mp4');
        Wl35DeviceMedia::create(['device_id' => 'fan-a', 'media_id' => $media->id, 'video_index' => 2]);
        $this->telemetry([
            $this->active('wl35', 'fan-a', 2), $this->active('wl35', 'fan-b', 2),
            array_merge($this->active('wl35', 'fan-a-invalid', 2), ['video_count' => 1]),
        ]);
        Wl35DeviceMedia::create(['device_id' => 'fan-a-invalid', 'media_id' => $media->id, 'video_index' => 2]);
        $previews = app(DevicePreviewService::class)->snapshot();
        $this->assertSame(route('media.content', $media), $previews[0]['url']);
        $this->assertSame('missing_mapping', $previews[1]['status']);
        $this->assertNull($previews[1]['url']);
        $this->assertSame('missing_mapping', $previews[2]['status']);
        $this->assertNull($previews[2]['url']);
    }

    public function test_inactive_unknown_and_missing_content_states_remove_the_source(): void
    {
        $this->media('campaign.mp4');
        $deleted = $this->media('deleted.mp4');
        $deleted->delete();
        $this->media('media/absent.mp4');
        $this->telemetry([
            array_merge($this->active('z2', 'offline', 'campaign.mp4'), ['online' => false]),
            array_merge($this->active('z2', 'off', 'campaign.mp4'), ['power' => false]),
            array_merge($this->active('z2', 'unknown', 'campaign.mp4'), ['power' => null]),
            $this->active('z2', 'empty', null), $this->active('z2', 'deleted', 'deleted.mp4'),
            $this->active('z2', 'unmatched', 'CAMPAIGN.mp4'),
            array_merge($this->active('wl35', 'session', 1), ['session_ready' => false]),
            $this->active('z2', 'missing-file', 'absent.mp4'),
        ]);
        $previews = app(DevicePreviewService::class)->snapshot();
        $this->assertSame(['offline', 'powered_off', 'unknown', 'no_content', 'missing_mapping', 'missing_mapping', 'unknown', 'missing_file'],
            array_column($previews, 'status'));
        $this->assertSame(array_fill(0, 8, null), array_column($previews, 'url'));
    }

    public function test_gateway_failure_falls_back_to_z2_telemetry_without_device_commands(): void
    {
        $media = $this->media('campaign.mp4');
        Http::fake([
            'gateway.test/*' => Http::response([], 503),
            'cloud.test/api/devices' => Http::response(['devices' => [
                ['deviceId' => 'AA', 'online' => true, 'power' => 1, 'displayImageId' => 'campaign.mp4'],
            ]]),
        ]);
        $previews = app(DevicePreviewService::class)->snapshot();
        $this->assertSame(route('media.content', $media), $previews[0]['url']);
        Http::assertSentCount(2);
        Http::assertNotSent(fn (Request $request) => $request->method() !== 'GET');
    }

    public function test_preview_and_media_endpoints_require_authorized_users(): void
    {
        $media = $this->media('campaign.mp4');
        $this->getJson(route('devices.previews'))->assertUnauthorized();
        $this->getJson(route('media.content', $media))->assertUnauthorized();
        $user = User::factory()->create();
        $user->role = 'viewer';
        $this->actingAs($user)->getJson(route('devices.previews'))->assertForbidden();
        $this->actingAs($user)->getJson(route('media.content', $media))->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_provider_failure_keeps_saved_devices_with_unavailable_previews(): void
    {
        Device::factory()->create(['mac_address' => 'AA:BB:CC:DD:EE:FF']);
        Wl35DeviceProfile::create(['device_id' => 'saved-fan', 'name' => 'Ventilador guardado']);
        Http::fake(['gateway.test/*' => Http::response([], 503), 'cloud.test/*' => Http::response([], 503)]);
        $this->actingAs(User::factory()->admin()->create())->getJson(route('devices.previews'))
            ->assertOk()->assertJsonCount(2, 'devices')
            ->assertJsonPath('devices.0.status', 'unavailable')->assertJsonPath('devices.0.url', null)
            ->assertJsonPath('devices.1.status', 'unavailable')->assertJsonPath('devices.1.url', null);
    }

    public function test_ambiguous_original_names_do_not_select_an_unverified_file(): void
    {
        $this->media('first.mp4', 'campaign.mp4');
        $this->media('second.mp4', 'campaign.mp4');
        $this->telemetry([$this->active('z2', 'fan-a', 'campaign.mp4')]);
        $this->assertSame('missing_mapping', app(DevicePreviewService::class)->snapshot()[0]['status']);
    }

    public function test_local_video_supports_byte_ranges_and_missing_files(): void
    {
        $media = $this->media('media/local.mp4');
        Storage::disk('public')->put($media->file_path, '0123456789');
        $this->actingAs(User::factory()->admin()->create());
        $response = $this->withHeader('Range', 'bytes=2-5')->get(route('media.content', $media));
        $response->assertStatus(206)->assertHeader('Content-Range', 'bytes 2-5/10')
            ->assertHeader('Content-Length', '4')->assertHeader('Content-Type', 'video/mp4');
        ob_start();
        $response->baseResponse->sendContent();
        $this->assertSame('2345', ob_get_clean());
        $this->withHeader('Range', 'bytes=20-30')->get(route('media.content', $media))->assertStatus(416);
        Storage::disk('public')->delete($media->file_path);
        $this->get(route('media.content', $media))->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_private_library_streams_range_bytes_without_exposing_credentials(): void
    {
        $media = $this->media('campaign.mp4');
        Http::fake(['cloud.test/fileDownload/Videos/campaign.mp4' => Http::response('2345', 206, [
            'Content-Length' => '4', 'Content-Range' => 'bytes 2-5/10', 'Accept-Ranges' => 'bytes',
        ])]);
        $this->actingAs(User::factory()->admin()->create())->withHeader('Range', 'bytes=2-5')
            ->get(route('media.content', $media))->assertStatus(206)
            ->assertHeader('Content-Range', 'bytes 2-5/10')->assertStreamedContent('2345');
        Http::assertSent(fn (Request $request) => $request->url() === 'http://cloud.test/fileDownload/Videos/campaign.mp4'
            && $request->hasHeader('Range', 'bytes=2-5')
            && $request->hasHeader('Authorization', 'Bearer cloud-test-token'));
    }

    public function test_untrusted_paths_are_not_read_or_requested_and_upstream_errors_are_preserved(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        foreach (['../outside.mp4', 'media/../../outside.mp4', 'https://other.test/video.mp4', 'C:\\video.mp4'] as $path) {
            $this->get(route('media.content', $this->media($path)))->assertNotFound();
        }
        Http::assertNothingSent();
        $media = $this->media('absent.mp4');
        Http::fake(['cloud.test/*' => Http::sequence()->push('', 404)
            ->push('invalid range', 416, ['Content-Range' => 'bytes */10', 'Content-Length' => '13'])
            ->push('', 302, ['Location' => 'http://other.test/video.mp4'])]);
        $this->get(route('media.content', $media))->assertNotFound();
        $this->withHeader('Range', 'bytes=20-30')->get(route('media.content', $media))
            ->assertStatus(416)->assertHeader('Content-Range', 'bytes */10')
            ->assertHeader('Content-Length', '13')->assertStreamedContent('invalid range');
        $this->get(route('media.content', $media))->assertStatus(502);
    }

    public function test_only_device_details_render_the_live_preview_interface(): void
    {
        $device = Device::factory()->create(['mac_address' => 'AA:BB:CC:DD:EE:FF']);
        Wl35DeviceProfile::create(['device_id' => 'fan-a', 'name' => 'Ventilador tienda']);
        Http::fake([
            'gateway.test/api/fleet' => Http::response(['devices' => [$this->active('wl35', 'fan-a', 2)]]),
            'cloud.test/api/devices*' => Http::response(['result' => 0, 'devices' => [], 'device' => [
                'online' => true, 'power' => 1, 'playlist' => [], 'volume' => 50,
            ]]),
            'cloud.test/api/media' => Http::response(['media' => []]),
        ]);
        $this->actingAs(User::factory()->admin()->create());
        $this->get(route('dashboard.index'))->assertOk()->assertDontSee('Vista previa del contenido')
            ->assertDontSee('devicePreviews(')->assertDontSee('mediaPreview(')
            ->assertDontSee(Js::from(route('devices.previews'))->toHtml(), false);
        foreach ([route('devices.show', $device), route('devices.wl35.show', 'fan-a')] as $url) {
            $this->get($url)->assertOk()->assertSee('Vista previa del contenido')
                ->assertSee('devicePreviews(')->assertSee(Js::from(route('devices.previews'))->toHtml(), false)
                ->assertSee('muted loop playsinline controls', false)->assertDontSee('autoplay', false);
        }
    }
}
