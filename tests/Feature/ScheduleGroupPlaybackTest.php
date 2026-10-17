<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\FleetUpload;
use App\Models\Group;
use App\Models\Media;
use App\Models\Schedule;
use App\Models\Wl35DeviceMedia;
use App\Models\Wl35DeviceProfile;
use App\Services\PrivateCloud\PrivateCloudClient;
use App\Services\ScheduleService;
use App\Services\Z2\Z2DeviceService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ScheduleGroupPlaybackTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['privatecloud.base_url' => 'http://cloud.test', 'privatecloud.token' => 'test-token',
            'unifiedfleet.base_url' => 'http://gateway.test', 'unifiedfleet.fallback_base_url' => '',
            'unifiedfleet.token' => 'test-token']);
        Http::preventStrayRequests();
        Storage::fake('local');
        Storage::fake('public');
    }

    private function media(string $path = 'campaign.mp4'): Media
    {
        return Media::forceCreate(['name' => 'Video de 28 segundos', 'file_path' => $path,
            'original_name' => 'campaign.mp4', 'mime_type' => 'video/mp4', 'duration' => 28, 'size' => 128]);
    }

    private function schedule(Media $media, array $attributes = []): Schedule
    {
        return Schedule::create(array_replace(['name' => 'Reproducir grupo', 'type' => 'change_content',
            'content_id' => $media->id, 'scheduled_at' => now()->subMinute(),
            'status' => 'pending', 'recurrence_type' => 'once'], $attributes));
    }

    private function profile(string $id, ?Group $group = null): Wl35DeviceProfile
    {
        return Wl35DeviceProfile::create(['device_id' => $id, 'name' => $id, 'group_id' => $group?->id]);
    }

    private function live(string $id, int $videos = 0): array
    {
        return ['id' => $id, 'key' => 'wl35:'.$id, 'type' => 'wl35', 'video_count' => $videos,
            'online' => true, 'connected' => true, 'session_ready' => true];
    }

    private function playbackResult(string $id, bool $success = true): array
    {
        return ['id' => $id, 'key' => 'wl35:'.$id, 'type' => 'wl35', 'success' => $success, 'video_index' => 1];
    }

    public function test_all_42_group_members_receive_the_same_resolved_file_once_without_gateway(): void
    {
        $media = $this->media('media/selected.mp4');
        Storage::disk('public')->put($media->file_path, 'complete-selected-bytes');
        $group = Group::factory()->create();
        foreach (['AA:BB', 'aabb', 'CCDD'] as $mac) {
            Device::factory()->online()->create(['group_id' => $group->id, 'mac_address' => $mac]);
        }
        Device::factory()->online()->create(['mac_address' => 'OUTSIDE']);
        $schedule = $this->schedule($media, ['group_id' => $group->id]);
        Http::fake(function (Request $request) {
            if (str_ends_with($request->url(), '/AABB/upload')) {
                $this->assertStringContainsString('complete-selected-bytes', $request->body());

                return Http::response(['result' => 0, 'filename' => 'resolved.mp4']);
            }
            $this->assertContains($request->url(), ['http://cloud.test/api/devices/AABB/play', 'http://cloud.test/api/devices/CCDD/play']);
            $this->assertSame('resolved.mp4', $request['filename']);

            return Http::response(['result' => 0]);
        });
        $this->artisan('schedules:process')->assertSuccessful();
        Http::assertSentCount(3);
        $this->assertSame('executed', $schedule->fresh()->status);
        $this->assertSame(28, $media->fresh()->duration);
        Storage::disk('public')->assertExists($media->file_path);
    }

    public function test_all_35_group_members_receive_complete_video_and_keep_library_mapping_without_user(): void
    {
        $media = $this->media('media/selected.mp4');
        $bytes = str_repeat('complete-video-28-seconds-', 128);
        Storage::disk('local')->put($media->file_path, $bytes);
        $group = Group::factory()->create();
        $this->profile('fan-a', $group);
        $this->profile('fan-b', $group);
        $this->profile('unrelated');
        $schedule = $this->schedule($media, ['group_id' => $group->id]);
        Http::fake(function (Request $request) use ($bytes) {
            if (str_ends_with($request->url(), '/api/fleet')) {
                return Http::response(['devices' => [$this->live('fan-a'), $this->live('fan-b'), $this->live('unrelated')]]);
            }
            if (str_ends_with($request->url(), '/api/health')) {
                return Http::response(['ok' => true]);
            }
            if (str_ends_with($request->url(), '/api/fleet/uploads')) {
                $this->assertSame($bytes, $request->body());
                $this->assertSame((string) strlen($bytes), $request->header('Content-Length')[0]);

                return Http::response(['upload_id' => 'scheduled-video'], 201);
            }
            $this->assertStringEndsWith('/scheduled-video/distribute', $request->url());
            $this->assertSame(['wl35:fan-a', 'wl35:fan-b'], $request['targets']);
            $this->assertTrue($request['play_after_upload']);

            return Http::response(['results' => [$this->playbackResult('fan-a'), $this->playbackResult('fan-b')]]);
        });
        $this->artisan('schedules:process')->assertSuccessful();
        $this->assertSame('executed', $schedule->fresh()->status);
        $this->assertSame(0, FleetUpload::count());
        $this->assertSame(2, Wl35DeviceMedia::where('media_id', $media->id)->count());
        $this->assertSame(28, $media->fresh()->duration);
        Storage::disk('local')->assertExists($media->file_path);
        $this->assertSame([], Storage::disk('local')->allFiles('schedule-playback'));
    }

    #[DataProvider('mixedOutcomes')]
    public function test_mixed_group_attempts_both_providers_and_reports_partial_failure(bool $z2, bool $wl35): void
    {
        $media = $this->media();
        $group = Group::factory()->create();
        Device::factory()->online()->create(['group_id' => $group->id, 'mac_address' => 'AABB']);
        $this->profile('fan-a', $group);
        $this->profile('outside');
        Wl35DeviceMedia::create(['device_id' => 'fan-a', 'media_id' => $media->id, 'video_index' => 1]);
        $schedule = $this->schedule($media, ['group_id' => $group->id]);
        Http::fake(['cloud.test/api/devices/AABB/play' => Http::response(['result' => $z2 ? 0 : -1]),
            'gateway.test/api/fleet' => Http::response(['devices' => [$this->live('fan-a', 1)]]),
            'gateway.test/api/health' => Http::response(['ok' => true]),
            'gateway.test/api/fleet/commands' => Http::response(['results' => [$this->playbackResult('fan-a', $wl35)]])]);
        $this->artisan('schedules:process')->assertSuccessful();
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/AABB/play') && $request['filename'] === 'campaign.mp4');
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/api/fleet/commands')
            && $request['targets'] === ['wl35:fan-a'] && $request['wl35_video_index'] === 1);
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/upload'));
        $this->assertSame($z2 && $wl35 ? 'executed' : 'failed', $schedule->fresh()->status);
        $this->assertSame($z2 && $wl35 ? 'success' : 'failed', $schedule->fresh()->last_run_status);
    }

    public static function mixedOutcomes(): array
    {
        return [[true, true], [false, true], [true, false]];
    }

    public function test_42_resolution_exception_does_not_skip_known_35_playback(): void
    {
        $media = $this->media();
        $group = Group::factory()->create();
        Device::factory()->online()->create(['group_id' => $group->id, 'mac_address' => 'AABB']);
        $this->profile('fan-a', $group);
        Wl35DeviceMedia::create(['device_id' => 'fan-a', 'media_id' => $media->id, 'video_index' => 1]);
        $schedule = $this->schedule($media, ['group_id' => $group->id]);
        $this->mock(Z2DeviceService::class)->shouldReceive('changeVideoOnDevices')->once()
            ->with(['AABB'], $media->file_path)->andThrow(new \RuntimeException('42 unavailable'));
        Http::fake(['gateway.test/api/fleet' => Http::response(['devices' => [$this->live('fan-a', 1)]]),
            'gateway.test/api/health' => Http::response(['ok' => true]),
            'gateway.test/api/fleet/commands' => Http::response(['results' => [$this->playbackResult('fan-a')]])]);
        $this->artisan('schedules:process')->assertSuccessful();
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/api/fleet/commands'));
        $this->assertSame('failed', $schedule->fresh()->status);
    }

    public function test_missing_42_result_is_failure_even_when_other_result_is_successful(): void
    {
        $media = $this->media();
        $group = Group::factory()->create();
        foreach (['AABB', 'CCDD'] as $mac) {
            Device::factory()->online()->create(['group_id' => $group->id, 'mac_address' => $mac]);
        }
        $schedule = $this->schedule($media, ['group_id' => $group->id]);
        $this->mock(Z2DeviceService::class)->shouldReceive('changeVideoOnDevices')->once()
            ->andReturn(['filename' => 'campaign.mp4', 'results' => ['AABB' => true]]);
        $this->artisan('schedules:process')->assertSuccessful();
        $this->assertSame('failed', $schedule->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_42_transport_exception_does_not_skip_later_devices_and_preserves_result_keys(): void
    {
        $media = $this->media();
        $client = $this->mock(PrivateCloudClient::class);
        $client->shouldReceive('post')->once()->with('/api/devices/AABB/play', ['filename' => 'campaign.mp4'])
            ->andThrow(new \RuntimeException('transport unavailable'));
        $client->shouldReceive('post')->once()->with('/api/devices/CCDD/play', ['filename' => 'campaign.mp4'])
            ->andReturn(['result' => 0]);
        $result = app(Z2DeviceService::class)->changeVideoOnDevices(['AA:BB', 'CCDD'], $media->file_path);
        $this->assertSame(['AA:BB' => false, 'CCDD' => true], $result['results']);
    }

    public function test_missing_35_result_is_failure_instead_of_partial_success(): void
    {
        $media = $this->media();
        $group = Group::factory()->create();
        foreach (['fan-a', 'fan-b'] as $id) {
            $this->profile($id, $group);
            Wl35DeviceMedia::create(['device_id' => $id, 'media_id' => $media->id, 'video_index' => 1]);
        }
        $schedule = $this->schedule($media, ['group_id' => $group->id]);
        Http::fake(['gateway.test/api/fleet' => Http::response(['devices' => [$this->live('fan-a', 1), $this->live('fan-b', 1)]]),
            'gateway.test/api/health' => Http::response(['ok' => true]),
            'gateway.test/api/fleet/commands' => Http::response(['results' => [$this->playbackResult('fan-a')]])]);
        $this->artisan('schedules:process')->assertSuccessful();
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/api/fleet/commands')
            && $request['targets'] === ['wl35:fan-a', 'wl35:fan-b']);
        $this->assertSame('failed', $schedule->fresh()->status);
    }

    public function test_individual_schedule_does_not_include_any_group_or_35_member(): void
    {
        $media = $this->media();
        $group = Group::factory()->create();
        $selected = Device::factory()->online()->create(['group_id' => $group->id, 'mac_address' => 'AABB']);
        Device::factory()->online()->create(['group_id' => $group->id, 'mac_address' => 'CCDD']);
        $this->profile('fan-a', $group);
        $schedule = $this->schedule($media, ['device_id' => $selected->id]);
        Http::fake(['cloud.test/api/devices/AABB/play' => Http::response(['result' => 0])]);
        $this->artisan('schedules:process')->assertSuccessful();
        Http::assertSentCount(1);
        $this->assertSame('executed', $schedule->fresh()->status);
    }

    public function test_all_devices_includes_registered_35_and_42_members(): void
    {
        $media = $this->media();
        Device::factory()->online()->create(['mac_address' => 'AABB']);
        $this->profile('fan-a');
        Wl35DeviceMedia::create(['device_id' => 'fan-a', 'media_id' => $media->id, 'video_index' => 1]);
        $schedule = $this->schedule($media);
        Http::fake(['cloud.test/api/devices/AABB/play' => Http::response(['result' => 0]),
            'gateway.test/api/fleet' => Http::response(['devices' => [$this->live('fan-a', 1), $this->live('unregistered')]]),
            'gateway.test/api/health' => Http::response(['ok' => true]),
            'gateway.test/api/fleet/commands' => Http::response(['results' => [$this->playbackResult('fan-a')]])]);
        $this->artisan('schedules:process')->assertSuccessful();
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/api/fleet/commands') && $request['targets'] === ['wl35:fan-a']);
        $this->assertSame('executed', $schedule->fresh()->status);
    }

    public function test_offline_35_does_not_turn_a_partial_distribution_into_success(): void
    {
        $media = $this->media('media/selected.mp4');
        Storage::disk('local')->put($media->file_path, 'complete-selected-video');
        $group = Group::factory()->create();
        $this->profile('fan-a', $group);
        $this->profile('offline', $group);
        $schedule = $this->schedule($media, ['group_id' => $group->id]);
        Http::fake(['gateway.test/api/fleet' => Http::response(['devices' => [
            $this->live('fan-a'), array_replace($this->live('offline'), ['online' => false]),
        ]]),
            'gateway.test/api/health' => Http::response(['ok' => true]),
            'gateway.test/api/fleet/uploads' => Http::response(['upload_id' => 'partial'], 201),
            'gateway.test/api/fleet/uploads/partial/distribute' => Http::response(['results' => [
                $this->playbackResult('fan-a'), $this->playbackResult('offline', false),
            ]]),
        ]);
        $this->artisan('schedules:process')->assertSuccessful();
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/partial/distribute')
            && $request['targets'] === ['wl35:fan-a', 'wl35:offline']);
        $this->assertSame('failed', $schedule->fresh()->status);
        $this->assertNotEmpty($schedule->fresh()->last_error);
        $this->assertSame([], Storage::disk('local')->allFiles('schedule-playback'));
    }

    public function test_blank_registered_member_is_reported_as_failure_in_a_mixed_group(): void
    {
        $media = $this->media();
        $group = Group::factory()->create();
        Device::factory()->create(['group_id' => $group->id, 'mac_address' => '']);
        $this->profile('fan-a', $group);
        Wl35DeviceMedia::create(['device_id' => 'fan-a', 'media_id' => $media->id, 'video_index' => 1]);
        $schedule = $this->schedule($media, ['group_id' => $group->id]);
        Http::fake(['gateway.test/api/fleet' => Http::response(['devices' => [$this->live('fan-a', 1)]]),
            'gateway.test/api/health' => Http::response(['ok' => true]),
            'gateway.test/api/fleet/commands' => Http::response(['results' => [$this->playbackResult('fan-a')]])]);
        $this->artisan('schedules:process')->assertSuccessful();
        $this->assertSame('failed', $schedule->fresh()->status);
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/api/fleet/commands'));
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'cloud.test'));
    }

    public function test_execution_lock_remains_owned_after_the_existing_long_playback_window(): void
    {
        $this->freezeTime();
        $media = $this->media();
        $group = Group::factory()->create();
        $this->profile('fan-a', $group);
        Wl35DeviceMedia::create(['device_id' => 'fan-a', 'media_id' => $media->id, 'video_index' => 1]);
        $schedule = $this->schedule($media, ['group_id' => $group->id]);
        Http::fake(function (Request $request) use ($schedule) {
            if (str_ends_with($request->url(), '/api/fleet')) {
                return Http::response(['devices' => [$this->live('fan-a', 1)]]);
            }
            if (str_ends_with($request->url(), '/api/health')) {
                return Http::response(['ok' => true]);
            }
            $this->travel(1901)->seconds();
            $this->assertFalse(Cache::lock('schedules:execute:'.$schedule->id, 3600)->get());
            app(ScheduleService::class)->execute($schedule->id);

            return Http::response(['results' => [$this->playbackResult('fan-a')]]);
        });
        $this->artisan('schedules:process')->assertSuccessful();
        Http::assertSentCount(2);
        $this->assertSame('executed', $schedule->fresh()->status);
    }

    #[DataProvider('emptyGroups')]
    public function test_empty_or_deleted_group_never_expands_into_all_devices(bool $deleted): void
    {
        $media = $this->media();
        $group = Group::factory()->create();
        Device::factory()->online()->create(['mac_address' => 'OUTSIDE']);
        $this->profile('outside');
        $schedule = $this->schedule($media, ['group_id' => $group->id]);
        if ($deleted) {
            $group->delete();
        }
        $this->artisan('schedules:process')->assertSuccessful();
        $this->assertSame('failed', $schedule->fresh()->status);
        Http::assertNothingSent();
    }

    public static function emptyGroups(): array
    {
        return [[false], [true]];
    }
}
