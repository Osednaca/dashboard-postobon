<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\User;
use App\Services\MediaService;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaDeleteTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config(['privatecloud.base_url' => 'http://private-cloud.test', 'privatecloud.token' => 'test-token']);
        Http::preventStrayRequests();
        Storage::fake('public');

        $this->user = User::factory()->create([
            'role' => 'admin',
        ]);
    }

    public function test_can_delete_single_media(): void
    {
        Storage::fake('public');

        $media = Media::create([
            'name' => "Test Video 'Quoted'",
            'original_name' => 'test.mp4',
            'file_path' => 'media/test.mp4',
            'mime_type' => 'video/mp4',
            'size' => 1024,
            'duration' => 10,
        ]);
        Storage::disk('public')->put($media->file_path, 'video bytes');
        config(['filesystems.default' => 'local']);

        $response = $this->actingAs($this->user)
            ->delete(route('media.destroy', $media));

        $response->assertRedirect(route('media.index'));
        $response->assertSessionHas('success');

        $this->assertSoftDeleted('media', ['id' => $media->id]);
        Storage::disk('public')->assertMissing($media->file_path);
        Http::assertNothingSent();
    }

    public function test_can_bulk_delete_media(): void
    {
        Storage::fake('public');

        $media1 = Media::create([
            'name' => 'Video 1',
            'original_name' => 'v1.mp4',
            'file_path' => 'media/v1.mp4',
            'mime_type' => 'video/mp4',
            'size' => 1024,
            'duration' => 5,
        ]);

        $media2 = Media::create([
            'name' => 'Video 2',
            'original_name' => 'v2.mp4',
            'file_path' => 'media/v2.mp4',
            'mime_type' => 'video/mp4',
            'size' => 2048,
            'duration' => 10,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('media.bulk-delete'), [
                'ids' => [$media1->id, $media2->id],
            ]);

        $response->assertRedirect(route('media.index'));
        $response->assertSessionHas('success');

        $this->assertSoftDeleted('media', ['id' => $media1->id]);
        $this->assertSoftDeleted('media', ['id' => $media2->id]);
    }

    private function cloudMedia(string $filename): Media
    {
        return Media::create([
            'name' => $filename, 'original_name' => $filename,
            'file_path' => $filename, 'mime_type' => 'video/mp4', 'size' => 10,
        ]);
    }

    public function test_failed_cloud_delete_preserves_web_record_and_reports_error(): void
    {
        $media = $this->cloudMedia('failed.mp4');
        Http::fake(['private-cloud.test/*' => Http::response(['result' => -1], 503)]);

        $this->actingAs($this->user)->delete(route('media.destroy', $media))
            ->assertSessionHas('error')->assertSessionMissing('success');

        $this->assertNotSoftDeleted($media);
    }

    public function test_failed_cloud_delete_preserves_api_record(): void
    {
        $media = $this->cloudMedia('failed.mp4');
        Http::fake(['private-cloud.test/*' => Http::response(['result' => -1])]);

        $this->actingAs($this->user)->deleteJson(route('api.media.destroy', $media))
            ->assertStatus(500)->assertJsonPath('message', 'Error al eliminar el medio.');

        $this->assertNotSoftDeleted($media);
    }

    public function test_bulk_web_delete_reports_partial_success_and_keeps_failed_records(): void
    {
        $deleted = $this->cloudMedia('deleted.mp4');
        $failed = $this->cloudMedia('failed.mp4');
        Http::fake([
            'private-cloud.test/api/media/deleted.mp4' => Http::response(['result' => 0]),
            'private-cloud.test/api/media/failed.mp4' => Http::response(['result' => -1], 503),
        ]);

        $this->actingAs($this->user)->post(route('media.bulk-delete'), ['ids' => [$deleted->id, $failed->id]])
            ->assertSessionHas('error', 'Se eliminaron 1 archivos; no se pudieron eliminar 1. Los medios fallidos se conservaron.')
            ->assertSessionMissing('success');

        $this->assertSoftDeleted($deleted);
        $this->assertNotSoftDeleted($failed);
    }

    public function test_bulk_api_delete_returns_counts_and_failed_ids(): void
    {
        $deleted = $this->cloudMedia('deleted.mp4');
        $failed = $this->cloudMedia('failed.mp4');
        Http::fake([
            'private-cloud.test/api/media/deleted.mp4' => Http::response(['result' => 0]),
            'private-cloud.test/api/media/failed.mp4' => Http::response(['result' => -1], 503),
        ]);

        $this->actingAs($this->user)->postJson(route('api.media.bulk-delete'), ['ids' => [$deleted->id, $failed->id]])
            ->assertStatus(207)->assertJsonPath('deleted_count', 1)->assertJsonPath('failed_ids', [$failed->id]);

        $this->assertSoftDeleted($deleted);
        $this->assertNotSoftDeleted($failed);
    }

    public function test_legacy_cloud_delete_uses_its_identified_filename(): void
    {
        $media = $this->cloudMedia('http://private-cloud.test/fileDownload/Videos/my%20video.mp4');
        Http::fake(['private-cloud.test/*' => Http::response(['result' => 0])]);

        $this->actingAs($this->user)->delete(route('media.destroy', $media))->assertSessionHas('success');

        Http::assertSent(fn ($request) => $request->method() === 'DELETE'
            && $request->url() === 'http://private-cloud.test/api/media/my%20video.mp4');
        $this->assertSoftDeleted($media);
    }

    public function test_deleting_external_reference_never_deletes_matching_cloud_filename(): void
    {
        $media = $this->cloudMedia('https://external.test/videos/other.mp4');

        $this->actingAs($this->user)->delete(route('media.destroy', $media))->assertSessionHas('success');

        Http::assertNothingSent();
        $this->assertSoftDeleted($media);
    }

    public function test_local_storage_failure_keeps_record_and_reports_error(): void
    {
        $media = $this->cloudMedia('media/local.mp4');
        $disk = \Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('exists')->twice()->with($media->file_path)->andReturnTrue();
        $disk->shouldReceive('delete')->once()->with($media->file_path)->andReturnFalse();
        Storage::shouldReceive('disk')->with('public')->andReturn($disk);

        $this->actingAs($this->user)->delete(route('media.destroy', $media))
            ->assertSessionHas('error')->assertSessionMissing('success');

        $this->assertNotSoftDeleted($media);
        Http::assertNothingSent();
    }

    public function test_thumbnail_cleanup_failure_does_not_keep_a_record_after_video_is_deleted(): void
    {
        $media = $this->cloudMedia('media/local.mp4');
        $media->update(['thumbnail' => 'media/poster.jpg']);
        $disk = \Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('exists')->with($media->file_path)->andReturnTrue();
        $disk->shouldReceive('delete')->with($media->file_path)->andReturnTrue();
        $disk->shouldReceive('exists')->with($media->thumbnail)->andReturnTrue();
        $disk->shouldReceive('delete')->with($media->thumbnail)->andReturnFalse();
        Storage::shouldReceive('disk')->with('public')->andReturn($disk);

        $this->actingAs($this->user)->delete(route('media.destroy', $media))->assertSessionHas('success');

        $this->assertSoftDeleted($media);
        Http::assertNothingSent();
    }

    public function test_legacy_local_upload_is_deleted_from_local_without_using_remote_default_disk(): void
    {
        Storage::fake('local');
        config(['filesystems.default' => 's3']);
        $media = $this->cloudMedia('media/legacy.mp4');
        Storage::disk('local')->put($media->file_path, 'bytes');

        $this->actingAs($this->user)->delete(route('media.destroy', $media))->assertSessionHas('success');

        Storage::disk('local')->assertMissing($media->file_path);
        $this->assertSoftDeleted($media);
        Http::assertNothingSent();
    }

    public function test_new_service_upload_uses_public_storage_despite_remote_default(): void
    {
        config(['filesystems.default' => 's3']);

        $media = app(MediaService::class)->upload(UploadedFile::fake()->create('local.mp4', 1, 'video/mp4'));

        Storage::disk('public')->assertExists($media->file_path);
        Http::assertNothingSent();
    }
}
