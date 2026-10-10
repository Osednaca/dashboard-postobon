<?php

namespace Tests\Feature;

use App\Jobs\GenerateMediaPreviewJob;
use App\Models\Media;
use App\Models\User;
use App\Models\Wl35DeviceMedia;
use App\Services\Fleet\CompressedMediaPreview;
use App\Services\Fleet\DevicePreviewService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CompressedMediaPreviewTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['queue.default' => 'database', 'privatecloud.base_url' => 'http://cloud.test',
            'unifiedfleet.base_url' => 'http://gateway.test', 'unifiedfleet.fallback_base_url' => '',
            'unifiedfleet.token' => 'test-token', 'queue.connections.database.retry_after' => 2100,
            'mediapreview.ffmpeg' => 'ffmpeg', 'mediapreview.ffprobe' => 'ffprobe']);
        Http::preventStrayRequests();
        Process::preventStrayProcesses();
        Queue::fake();
        Storage::fake('public');
        Storage::fake('local');
    }

    private function media(): Media
    {
        Storage::disk('public')->put('media/original.mp4', str_repeat('original-video', 100));

        return Media::create(['name' => 'Video completo', 'original_name' => 'original.mp4',
            'file_path' => 'media/original.mp4', 'mime_type' => 'video/mp4', 'size' => 1400, 'duration' => 28]);
    }

    private function converter(string $output = 'compressed-video', bool $fail = false, float $duration = 28.28): void
    {
        Process::fake(function ($process) use ($output, $fail, $duration) {
            if ($process->command[0] === 'ffmpeg') {
                if ($fail) {
                    return Process::result(exitCode: 1);
                }
                file_put_contents(end($process->command), $output);

                return Process::result();
            }
            $isOutput = str_ends_with($process->command[array_search('-i', $process->command) + 1], '.output.mp4');

            return Process::result(json_encode(['streams' => [['codec_name' => 'h264', 'width' => 448,
                'height' => 252, 'duration' => $isOutput ? $duration : 28.28]]]));
        });
    }

    public function test_polling_deduplicates_and_never_runs_a_process_in_the_request(): void
    {
        $media = $this->media();
        $service = app(CompressedMediaPreview::class);
        $this->assertSame(route('media.content', $media), $service->url($media));
        $this->assertSame(route('media.content', $media), $service->url($media));
        Queue::assertPushed(GenerateMediaPreviewJob::class, 1);
        Queue::assertPushed(GenerateMediaPreviewJob::class, fn ($job) => $job->queue === 'previews' && $job->timeout === 360);
        Process::assertNothingRan();
    }

    public static function unsafeQueues(): array
    {
        return array_map(fn ($driver) => [$driver], ['sync', 'deferred', 'background', 'null', 'failover', 'redis']);
    }

    #[DataProvider('unsafeQueues')]
    public function test_inline_or_short_retry_connections_always_fall_back(string $connection): void
    {
        config(['queue.default' => $connection, 'queue.connections.redis.retry_after' => 90]);
        $media = $this->media();
        $this->assertSame(route('media.content', $media), app(CompressedMediaPreview::class)->url($media));
        Queue::assertNothingPushed();
        Process::assertNothingRan();
    }

    public function test_ready_copy_changes_url_once_preserves_original_and_serves_authorized_range(): void
    {
        $media = $this->media();
        $original = Storage::disk('public')->get($media->file_path);
        $service = app(CompressedMediaPreview::class);
        $this->converter();
        $service->generate($media->id, $service->identity($media));
        $url = $service->url($media);
        $this->assertStringContainsString('?preview=', $url);
        $this->assertSame($url, $service->url($media));
        $media->touch();
        $this->assertSame($url, $service->url($media->fresh()));
        $this->assertSame($original, Storage::disk('public')->get($media->file_path));
        $this->assertSame(28, $media->fresh()->duration);
        Process::assertRan(function ($process) {
            return $process->command[0] === 'ffmpeg' && in_array('file,pipe', $process->command, true)
                && in_array('mov', $process->command, true) && in_array('-an', $process->command, true)
                && ! in_array('-t', $process->command, true) && ! in_array('-ss', $process->command, true);
        });
        $this->get($url)->assertRedirect(route('login'));
        $unauthorized = User::factory()->create();
        $unauthorized->role = 'viewer';
        $this->actingAs($unauthorized)->get($url)->assertForbidden();
        $this->actingAs(User::factory()->admin()->create());
        $this->get($url, ['Range' => 'bytes=0-3'])->assertStatus(206)
            ->assertHeader('Content-Range', 'bytes 0-3/16')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get(route('media.content', $media).'?preview=../../anything')->assertNotFound();
        $this->get(route('media.content', $media).'?preview[]=anything')->assertNotFound();
        $this->get(route('media.content', $media))->assertOk()->assertHeader('Content-Length', (string) strlen($original));
        Http::assertNothingSent();
    }

    public static function failures(): array
    {
        return [['tiny', true, 28.28], [str_repeat('large', 400), false, 28.28], ['truncated', false, 10.0]];
    }

    #[DataProvider('failures')]
    public function test_failed_larger_or_truncated_outputs_use_original_and_clean_temporary_files(string $output, bool $fail, float $duration): void
    {
        $media = $this->media();
        $service = app(CompressedMediaPreview::class);
        $this->converter($output, $fail, $duration);
        $service->generate($media->id, $service->identity($media));
        $this->assertSame(route('media.content', $media), $service->url($media));
        $this->assertSame([], Storage::disk('local')->allFiles('media-previews'));
        Queue::assertNothingPushed();
        $this->travel(11)->minutes();
        if ($fail || $duration === 10.0) {
            $service->url($media);
            Queue::assertPushed(GenerateMediaPreviewJob::class, 1);
        }
    }

    public function test_source_change_invalidates_copy_without_using_routine_updated_at(): void
    {
        $media = $this->media();
        $service = app(CompressedMediaPreview::class);
        $this->converter();
        $oldIdentity = $service->identity($media);
        $service->generate($media->id, $oldIdentity);
        $oldUrl = $service->url($media);
        Storage::disk('public')->put($media->file_path, 'replacement-of-different-size');
        $this->assertNotSame($oldIdentity, $service->identity($media));
        $this->assertSame(route('media.content', $media), $service->url($media));
        $this->actingAs(User::factory()->admin()->create())->get($oldUrl)->assertNotFound();
    }

    public function test_remote_source_revalidated_after_one_hour_and_only_z2_schedules_conversion(): void
    {
        $media = $this->media();
        $media->update(['file_path' => 'remote.mp4']);
        $service = app(CompressedMediaPreview::class);
        $identity = $service->identity($media);
        $variant = str_repeat('a', 64);
        Storage::disk('local')->put('media-previews/'.$identity.'/'.$variant.'.mp4', 'compressed');
        Cache::put('media-preview:'.$identity, ['state' => 'ready', 'variant' => $variant, 'checked_at' => now()->timestamp], 86400);
        Http::fake(['gateway.test/api/fleet' => Http::response(['devices' => [[
            'type' => 'z2', 'id' => 'AABB', 'online' => true, 'power' => true, 'current_video' => 'remote.mp4',
        ]]]), 'cloud.test/api/devices' => Http::response(['devices' => []])]);
        $this->assertStringContainsString('?preview=', app(DevicePreviewService::class)->snapshot()[0]['url']);
        Queue::assertNothingPushed();
        $this->travel(61)->minutes();
        $this->assertSame(route('media.content', $media), app(DevicePreviewService::class)->snapshot()[0]['url']);
        Queue::assertPushed(GenerateMediaPreviewJob::class, 1);
    }

    public function test_non_mp4_oversize_and_deleted_source_do_not_convert_or_publish(): void
    {
        $media = $this->media();
        $service = app(CompressedMediaPreview::class);
        $identity = $service->identity($media);
        $media->update(['mime_type' => 'video/webm']);
        $this->assertNull($service->identity($media));
        $media->update(['mime_type' => 'video/mp4', 'size' => config('mediapreview.max_source_bytes') + 1]);
        $this->assertNull($service->identity($media));
        $media->delete();
        $service->generate($media->id, $identity);
        Process::assertNothingRan();
        $this->assertSame([], Storage::disk('local')->allFiles('media-previews'));
    }

    public function test_missing_copy_is_requeued_and_an_active_copy_survives_pruning(): void
    {
        $media = $this->media();
        $service = app(CompressedMediaPreview::class);
        $this->converter();
        $service->generate($media->id, $service->identity($media));
        $url = $service->url($media);
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $path = $service->file($media, $query['preview']);
        touch($path, now()->subDays(8)->timestamp);
        $this->assertSame($url, $service->url($media));
        $service->prune();
        $this->assertFileExists($path);
        unlink($path);
        $this->assertSame(route('media.content', $media), $service->url($media));
        Queue::assertPushed(GenerateMediaPreviewJob::class, 1);
    }

    public function test_wl35_keeps_original_and_does_not_schedule_a_derivative(): void
    {
        $media = $this->media();
        Wl35DeviceMedia::create(['device_id' => 'fan-a', 'video_index' => 1, 'media_id' => $media->id]);
        Http::fake(['gateway.test/api/fleet' => Http::response(['devices' => [[
            'type' => 'wl35', 'id' => 'fan-a', 'online' => true, 'power' => true, 'connected' => true,
            'session_ready' => true, 'current_video' => 1, 'video_count' => 1,
        ]]]), 'cloud.test/api/devices' => Http::response(['devices' => []])]);
        $this->assertSame(route('media.content', $media), app(DevicePreviewService::class)->snapshot()[0]['url']);
        Queue::assertNothingPushed();
        Process::assertNothingRan();
    }

    public function test_private_bytes_are_copied_from_fixed_origin_and_reused_when_unchanged(): void
    {
        $media = $this->media();
        $media->update(['file_path' => 'remote.mp4']);
        Http::fake(function ($request, $options) {
            $this->assertSame('http://cloud.test/fileDownload/Videos/remote.mp4', $request->url());
            $this->assertEquals(120, $options['timeout']);
            $this->assertFalse($options['allow_redirects']);
            $this->assertArrayHasKey('progress', $options);
            $bytes = str_repeat('full-private-video', 100);
            file_put_contents($options['sink'], $bytes);

            return Http::response($bytes, 200, ['ETag' => 'provider-etag']);
        });
        $service = app(CompressedMediaPreview::class);
        $this->converter();
        $service->generate($media->id, $service->identity($media));
        $url = $service->url($media);
        $this->assertStringContainsString('?preview=', $url);
        $this->travel(61)->minutes();
        $service->generate($media->id, $service->identity($media));
        $this->assertSame($url, $service->url($media));
        Http::assertSentCount(2);
        Process::assertRanTimes(fn ($process) => $process->command[0] === 'ffmpeg', 1);
        $this->assertCount(1, Storage::disk('local')->allFiles('media-previews'));
    }

    public function test_private_download_failure_leaves_original_available_without_running_ffmpeg(): void
    {
        $media = $this->media();
        $media->update(['file_path' => 'remote.mp4']);
        Http::fake(['cloud.test/*' => Http::response('', 503)]);
        $service = app(CompressedMediaPreview::class);
        $service->generate($media->id, $service->identity($media));
        $this->assertSame(route('media.content', $media), $service->url($media));
        Process::assertNothingRan();
        $this->assertSame([], Storage::disk('local')->allFiles('media-previews'));
    }

    public function test_pruning_only_removes_old_owned_preview_files(): void
    {
        $disk = Storage::disk('local');
        $old = 'media-previews/'.str_repeat('a', 64).'/'.str_repeat('b', 64).'.mp4';
        $disk->put($old, 'old');
        touch($disk->path($old), now()->subDays(8)->timestamp);
        $disk->put('media-previews/unrelated.mp4', 'unrelated');
        app(CompressedMediaPreview::class)->prune();
        $disk->assertMissing($old);
        $disk->assertExists('media-previews/unrelated.mp4');
    }
}
