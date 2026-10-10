<?php

namespace Tests\Feature;

use App\Jobs\DistributeFleetVideoJob;
use App\Jobs\FormatFleetStorageJob;
use App\Jobs\PlayFleetMediaJob;
use App\Models\FleetOperation;
use App\Models\FleetUpload;
use App\Models\Media;
use App\Models\User;
use App\Models\Wl35DeviceMedia;
use App\Services\Fleet\DevicePreviewService;
use App\Services\Fleet\UnifiedFleetClient;
use App\Services\Fleet\Wl35MediaMapping;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class Wl35LibraryUploadTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['unifiedfleet.base_url' => 'http://gateway.test', 'unifiedfleet.fallback_base_url' => '',
            'unifiedfleet.token' => 'test-token', 'privatecloud.base_url' => 'http://cloud.test',
            'privatecloud.token' => 'test-token', 'queue.default' => 'database']);
        Http::preventStrayRequests();
        Storage::fake('local');
        Storage::fake('public');
        $this->actingAs(User::factory()->admin()->create());
    }

    private function media(string $path = 'media/campaign.mp4'): Media
    {
        return Media::create(['name' => 'Campaña', 'original_name' => 'gaseosas.mp4',
            'file_path' => $path, 'mime_type' => 'video/mp4', 'size' => 128, 'duration' => 28]);
    }

    private function operation(?Media $media = null): FleetUpload
    {
        return FleetUpload::create(['user_id' => auth()->id(), 'source_media_id' => $media?->id,
            'original_name' => $media?->original_name ?? 'directo.mp4', 'file_path' => 'fleet-uploads/test.mp4',
            'targets' => ['wl35:fan-a'], 'status' => 'queued', 'progress' => 15]);
    }

    private function live(int $count = 4): array
    {
        return ['id' => 'fan-a', 'type' => 'wl35', 'key' => 'wl35:fan-a', 'name' => 'WL35',
            'online' => true, 'connected' => true, 'session_ready' => true, 'power' => true,
            'video_count' => $count, 'current_video' => 1];
    }

    private function uploadResult(int $index = 2): array
    {
        return ['key' => 'wl35:fan-a', 'type' => 'wl35', 'id' => 'fan-a', 'success' => true, 'video_index' => $index];
    }

    public function test_detail_names_are_scoped_to_known_reported_indices_and_upload_sources_are_available(): void
    {
        $media = $this->media();
        Wl35DeviceMedia::create(['device_id' => 'fan-a', 'media_id' => $media->id, 'video_index' => 1]);
        Wl35DeviceMedia::create(['device_id' => 'fan-a', 'filename' => 'desde-equipo.mp4', 'video_index' => 2]);
        Wl35DeviceMedia::create(['device_id' => 'fan-a', 'filename' => 'stale.mp4', 'video_index' => 8]);
        Wl35DeviceMedia::create(['device_id' => 'fan-b', 'filename' => 'other-device.mp4', 'video_index' => 3]);
        Media::create(['name' => 'Imagen excluida', 'original_name' => 'imagen.png',
            'file_path' => 'media/imagen.png', 'mime_type' => 'image/png', 'size' => 10]);
        Http::fake(['gateway.test/api/fleet' => Http::response(['devices' => [$this->live()]])]);

        $this->get(route('devices.wl35.show', 'fan-a'))->assertOk()
            ->assertSee('gaseosas.mp4')->assertSee('desde-equipo.mp4')
            ->assertSee('Nombre de archivo no identificado')->assertSee('índice 1')
            ->assertSee('Desde este equipo')->assertSee('Desde la biblioteca')
            ->assertSee('name="video"', false)->assertSee('name="media_id"', false)
            ->assertSee('Esta acción no inicia la reproducción.')
            ->assertDontSee('stale.mp4')->assertDontSee('other-device.mp4')->assertDontSee('Imagen excluida');
    }

    public function test_library_and_direct_uploads_use_the_existing_upload_only_job(): void
    {
        $media = $this->media();
        Bus::fake();
        foreach ([['media_id' => $media->id], ['video' => UploadedFile::fake()->create('directo.mp4', 1, 'video/mp4')]] as $source) {
            $response = $this->postJson(route('fleet.upload'), $source + ['targets' => ['wl35:fan-a']])->assertAccepted();
            $operation = FleetUpload::findOrFail($response->json('upload_id'));
            $this->assertSame(isset($source['media_id']) ? $media->id : null, $operation->source_media_id);
            $this->assertFalse($operation->play_after_upload);
            $this->assertSame(['wl35:fan-a'], $operation->targets);
            Bus::assertDispatched(DistributeFleetVideoJob::class, fn ($job) => $job->fleetUploadId === $operation->id);
        }
        Bus::assertNotDispatched(PlayFleetMediaJob::class);
        Http::assertNothingSent();
    }

    public function test_upload_rejects_missing_conflicting_deleted_and_non_video_sources(): void
    {
        $media = $this->media();
        $image = Media::create(['name' => 'Imagen', 'original_name' => 'image.png',
            'file_path' => 'image.png', 'mime_type' => 'image/png', 'size' => 1]);
        Bus::fake();
        $target = ['targets' => ['wl35:fan-a']];
        $this->postJson(route('fleet.upload'), $target)->assertUnprocessable();
        $this->postJson(route('fleet.upload'), $target + ['media_id' => $media->id,
            'video' => UploadedFile::fake()->create('directo.mp4', 1, 'video/mp4')])->assertUnprocessable();
        $this->postJson(route('fleet.upload'), $target + ['media_id' => $image->id])->assertUnprocessable();
        $media->delete();
        $this->postJson(route('fleet.upload'), $target + ['media_id' => $media->id])->assertUnprocessable();
        Bus::assertNothingDispatched();
        Http::assertNothingSent();
    }

    #[DataProvider('sources')]
    public function test_worker_sends_complete_source_without_playing_and_remembers_confirmed_names(string $source): void
    {
        $media = $source === 'direct' ? null : $this->media($source === 'cloud' ? 'campaign.mp4' : 'media/campaign.mp4');
        $upload = $this->operation($media);
        $bytes = str_repeat('complete-source-', 20);
        if ($source === 'direct') {
            Storage::disk('local')->put($upload->file_path, $bytes);
        } elseif ($source === 'local') {
            Storage::disk('public')->put($media->file_path, $bytes);
        }
        Http::fake(function (Request $request, array $options) use ($bytes) {
            if (str_starts_with($request->url(), 'http://cloud.test/')) {
                file_put_contents($options['sink'], $bytes);

                return Http::response($bytes);
            }
            if (str_ends_with($request->url(), '/api/health')) {
                return Http::response(['ok' => true]);
            }
            if (str_ends_with($request->url(), '/api/fleet/uploads')) {
                $this->assertSame($bytes, $request->body());

                return Http::response(['upload_id' => 'gateway-upload']);
            }
            $this->assertSame(['wl35:fan-a'], $request['targets']);
            $this->assertFalse($request['play_after_upload']);

            return Http::response(['success' => true, 'succeeded' => 1, 'failed' => 0, 'results' => [$this->uploadResult()]]);
        });

        (new DistributeFleetVideoJob($upload->id))->handle(app(UnifiedFleetClient::class));
        $mapping = Wl35DeviceMedia::where('device_id', 'fan-a')->sole();
        $this->assertSame(2, $mapping->video_index);
        $this->assertSame($media?->id, $mapping->media_id);
        $this->assertSame($upload->original_name, $mapping->filename);
        $this->assertSame('completed', $upload->fresh()->status);
        Storage::disk('local')->assertMissing($upload->file_path);
        if ($source === 'local') {
            Storage::disk('public')->assertExists($media->file_path);
        }
    }

    public static function sources(): array
    {
        return [['direct'], ['local'], ['cloud']];
    }

    public function test_missing_library_source_aborts_without_upload_or_mapping(): void
    {
        $upload = $this->operation($this->media());
        Http::fake();
        try {
            (new DistributeFleetVideoJob($upload->id))->handle(app(UnifiedFleetClient::class));
            $this->fail('Missing source must fail.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('encontrar', $exception->getMessage());
        }
        $this->assertSame('failed', $upload->fresh()->status);
        $this->assertSame(0, Wl35DeviceMedia::count());
        Http::assertNothingSent();
    }

    public function test_mappings_require_explicit_upload_index_and_target_and_preserve_prior_file_labels(): void
    {
        $media = $this->media();
        $mapping = app(Wl35MediaMapping::class);
        $invalid = [$this->uploadResult(0), $this->uploadResult(256), array_diff_key($this->uploadResult(), ['video_index' => true]),
            array_replace($this->uploadResult(), ['success' => false]), array_replace($this->uploadResult(), ['key' => 'wl35:fan-b']),
            array_replace($this->uploadResult(), ['id' => 'fan-b', 'key' => 'wl35:fan-b'])];
        $mapping->remember($invalid, ['wl35:fan-a'], $media->id, 'gaseosas.mp4');
        $this->assertSame(0, Wl35DeviceMedia::count());
        $mapping->remember([$this->uploadResult(1)], ['wl35:fan-a'], $media->id, 'primero.mp4');
        $mapping->remember([$this->uploadResult(2)], ['wl35:fan-a'], $media->id, 'segundo.mp4');
        $this->assertNull(Wl35DeviceMedia::where('video_index', 1)->sole()->media_id);
        $this->assertSame('primero.mp4', Wl35DeviceMedia::where('video_index', 1)->sole()->filename);
        $this->assertSame($media->id, Wl35DeviceMedia::where('video_index', 2)->sole()->media_id);
        $media->forceDelete();
        $this->assertSame('segundo.mp4', Wl35DeviceMedia::where('video_index', 2)->sole()->filename);
        $this->assertNull(Wl35DeviceMedia::where('video_index', 2)->sole()->media_id);
    }

    public function test_null_media_labels_survive_preview_resolution_and_shift_on_delete_and_clear_on_format(): void
    {
        Wl35DeviceMedia::create(['device_id' => 'fan-a', 'filename' => 'actual.mp4', 'video_index' => 1]);
        Wl35DeviceMedia::create(['device_id' => 'fan-a', 'filename' => 'siguiente.mp4', 'video_index' => 2]);
        Http::fake(['gateway.test/api/fleet' => Http::response(['devices' => [$this->live(2)]]),
            'cloud.test/api/devices' => Http::response(['devices' => []])]);
        $preview = collect(app(DevicePreviewService::class)->snapshot())->firstWhere('key', 'wl35:fan-a');
        $this->assertSame('missing_mapping', $preview['status']);
        $this->assertSame('actual.mp4', $preview['media_name']);
        $this->assertNull($preview['url']);
        Http::fake(['gateway.test/*' => Http::response(['success' => true, 'results' => [
            ['success' => true, 'type' => 'wl35', 'id' => 'fan-a'],
        ]])]);
        $this->delete(route('devices.wl35.videos.destroy', 'fan-a'), ['index' => 1])->assertRedirect();
        $this->assertSame('siguiente.mp4', Wl35DeviceMedia::sole()->filename);
        $this->assertSame(1, Wl35DeviceMedia::sole()->video_index);
        $operation = FleetOperation::create(['user_id' => auth()->id(), 'command' => 'format_sd',
            'targets' => ['wl35:fan-a'], 'payload' => ['command' => 'format_sd', 'targets' => ['wl35:fan-a']], 'status' => 'queued']);
        (new FormatFleetStorageJob($operation->id))->handle(app(UnifiedFleetClient::class));
        $this->assertSame(0, Wl35DeviceMedia::count());
    }

    public function test_rollback_refuses_to_silently_discard_direct_upload_names(): void
    {
        Wl35DeviceMedia::create(['device_id' => 'fan-a', 'filename' => 'directo.mp4', 'video_index' => 1]);
        $migration = require database_path('migrations/2026_10_10_000001_add_filename_to_wl35_device_media_table.php');
        try {
            $migration->down();
            $this->fail('Rollback must preserve orphan labels.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('respaldar', $exception->getMessage());
        }
        $this->assertTrue(Schema::hasColumn('wl35_device_media', 'filename'));
        $this->assertSame('directo.mp4', Wl35DeviceMedia::sole()->filename);
    }

    public function test_migration_roundtrip_preserves_existing_library_mapping_and_backfills_filename(): void
    {
        $media = $this->media();
        $mapping = Wl35DeviceMedia::create(['device_id' => 'fan-a', 'media_id' => $media->id, 'video_index' => 1]);
        $migration = require database_path('migrations/2026_10_10_000001_add_filename_to_wl35_device_media_table.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('wl35_device_media', 'filename'));
        $this->assertSame($media->id, $mapping->fresh()->media_id);
        $migration->up();
        $this->assertSame('gaseosas.mp4', $mapping->fresh()->filename);
        $media->forceDelete();
        $this->assertNull($mapping->fresh()->media_id);
        $this->assertSame('gaseosas.mp4', $mapping->fresh()->filename);
    }
}
