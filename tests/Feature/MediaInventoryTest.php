<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Services\MediaService;
use App\Services\Z2\Z2VideoService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MediaInventoryTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['privatecloud.base_url' => 'http://cloud.test']);
        Http::preventStrayRequests();
        Storage::fake('public');
    }

    private function media(string $path): Media
    {
        return Media::create([
            'name' => 'Nombre editado', 'original_name' => 'Original.mov', 'file_path' => $path,
            'mime_type' => 'video/quicktime', 'size' => 12, 'duration' => 45, 'thumbnail' => 'media/poster.jpg',
        ]);
    }

    public static function invalidInventories(): array
    {
        return [
            'http failure' => [['result' => -1], 503],
            'logical failure' => [['result' => -1, 'media' => []], 200],
            'missing inventory' => [['result' => 0], 200],
            'missing result' => [['media' => []], 200],
            'wrong inventory type' => [['result' => 0, 'media' => ''], 200],
            'partial row' => [['result' => 0, 'media' => [['filename' => 'other.mp4']]], 200],
            'unsafe filename' => [['result' => 0, 'media' => [['filename' => '../other.mp4', 'size' => 10]]], 200],
            'duplicate row' => [['result' => 0, 'media' => array_fill(0, 2, ['filename' => 'other.mp4', 'size' => 10])], 200],
        ];
    }

    #[DataProvider('invalidInventories')]
    public function test_failed_or_incomplete_inventory_never_purges_records(array $body, int $status): void
    {
        $media = $this->media('keep.mp4');
        Http::fake(['cloud.test/api/media' => Http::response($body, $status)]);

        app(Z2VideoService::class)->syncVideos();

        $this->assertNotSoftDeleted($media);
        $this->assertDatabaseCount('media', 1);
        $this->assertSame(45, $media->fresh()->duration);
    }

    public function test_empty_valid_inventory_removes_only_identified_private_library_sources(): void
    {
        $cloud = $this->media('gone.mp4');
        $legacy = $this->media('http://cloud.test/fileDownload/Videos/old%20video.mp4');
        $local = $this->media('media/upload.mp4');
        $bareLocal = $this->media('local.mp4');
        Storage::disk('public')->put('local.mp4', 'local bytes');
        $external = $this->media('https://external.test/fileDownload/Videos/old.mp4');
        $otherRoute = $this->media('http://cloud.test/other/old.mp4');
        Http::fake(['cloud.test/api/media' => Http::response(['result' => 0, 'media' => []])]);

        app(Z2VideoService::class)->syncVideos();

        $this->assertSoftDeleted($cloud);
        $this->assertSoftDeleted($legacy);
        foreach ([$local, $bareLocal, $external, $otherRoute] as $media) {
            $this->assertNotSoftDeleted($media);
        }
        Storage::disk('public')->assertExists('local.mp4');
    }

    public function test_sync_updates_available_size_without_overwriting_local_metadata(): void
    {
        $media = $this->media('present.mov');
        Http::fake(['cloud.test/api/media' => Http::response(['result' => 0, 'media' => [
            ['filename' => 'present.mov', 'size' => 99], ['filename' => 'new.mp4', 'size' => 10],
        ]])]);

        app(Z2VideoService::class)->syncVideos();

        $media->refresh();
        $this->assertSame(99, $media->size);
        $this->assertSame('Nombre editado', $media->name);
        $this->assertSame('Original.mov', $media->original_name);
        $this->assertSame('video/quicktime', $media->mime_type);
        $this->assertSame(45, $media->duration);
        $this->assertSame('media/poster.jpg', $media->thumbnail);
        $this->assertDatabaseHas('media', ['file_path' => 'new.mp4', 'duration' => null]);
    }

    public function test_legacy_source_is_canonicalized_without_changing_id_or_metadata(): void
    {
        $media = $this->media('http://cloud.test/fileDownload/Videos/my%20video.mp4');
        Http::fake(['cloud.test/api/media' => Http::response(['result' => 0, 'media' => [
            ['filename' => 'my video.mp4', 'size' => 99],
        ]])]);

        app(Z2VideoService::class)->syncVideos();

        $this->assertSame('my video.mp4', $media->fresh()->file_path);
        $this->assertSame(45, $media->fresh()->duration);
        $this->assertDatabaseCount('media', 1);
    }

    public function test_sync_does_not_resurrect_tombstones_or_keep_active_legacy_aliases(): void
    {
        $media = $this->media('deleted.mp4');
        $media->delete();
        $alias = $this->media('http://cloud.test/fileDownload/Videos/deleted.mp4');
        Http::fake(['cloud.test/api/media' => Http::response(['result' => 0, 'media' => [
            ['filename' => 'deleted.mp4', 'size' => 99],
        ]])]);

        app(Z2VideoService::class)->syncVideos();

        $this->assertSoftDeleted($media);
        $this->assertSoftDeleted($alias);
        $this->assertSame(0, Media::count());
    }

    public function test_matching_bare_local_source_is_preserved_without_unique_conflict(): void
    {
        $media = $this->media('local.mp4');
        Storage::disk('public')->put('local.mp4', 'bytes');
        Http::fake(['cloud.test/api/media' => Http::response(['result' => 0, 'media' => [
            ['filename' => 'local.mp4', 'size' => 99],
        ]])]);

        app(Z2VideoService::class)->syncVideos();

        $this->assertSame(12, $media->fresh()->size);
        $this->assertDatabaseCount('media', 1);
    }

    public function test_explicit_reupload_restores_deleted_filename_and_remains_visible_after_sync(): void
    {
        $media = $this->media('again.mp4');
        Http::fake([
            'cloud.test/api/media/again.mp4' => Http::response(['result' => 0]),
            'cloud.test/api/media/upload' => Http::response(['result' => 0, 'filename' => 'again.mp4', 'size' => 99]),
            'cloud.test/api/media' => Http::response(['result' => 0, 'media' => [['filename' => 'again.mp4', 'size' => 99]]]),
        ]);
        app(MediaService::class)->delete($media->id);
        $this->assertSoftDeleted($media);
        Storage::disk('public')->put('media/upload.mp4', 'new bytes');

        $uploaded = app(Z2VideoService::class)->uploadVideo(Storage::disk('public')->path('media/upload.mp4'), 'again.mp4', 20);
        app(Z2VideoService::class)->syncVideos();

        $this->assertSame($media->id, $uploaded->id);
        $this->assertNotSoftDeleted($media);
        $this->assertSame(20, $media->fresh()->duration);
        $this->assertDatabaseCount('media', 1);
    }
}
