<?php

namespace Tests\Feature;

use App\Models\CampaignDevicePlayback;
use App\Models\Media;
use App\Models\Wl35DeviceMedia;
use App\Services\Campaigns\CampaignBaselineBackup;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class CampaignBaselineBackupTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Storage::fake('local');
        Storage::fake('public');
        Storage::fake('backup');
        config(['campaigns.backup_disk' => 'backup', 'campaigns.backup_persistent' => true, 'filesystems.disks.backup.driver' => 'local', 'privatecloud.base_url' => 'http://cloud.test']);
    }

    private function playback(array $baseline, string $target = 'z2:ABC'): CampaignDevicePlayback
    {
        return CampaignDevicePlayback::create(['target_key' => $target, 'baseline' => $baseline + ['session_id' => (string) Str::uuid()]]);
    }

    private function media(string $filename, string $bytes = 'example video'): Media
    {
        Storage::disk('local')->put($filename, $bytes);

        return Media::factory()->video()->create(['file_path' => $filename]);
    }

    public function test_copies_one_per_call_and_verifies_every_durable_copy_before_completion(): void
    {
        $this->media('a.mp4', 'first');
        $this->media('b.mp4', 'second');
        $playback = $this->playback(['type' => 'z2', 'files' => ['a.mp4', 'b.mp4']]);
        $service = app(CampaignBaselineBackup::class);

        $this->assertFalse($service->prepare($playback));
        $this->assertCount(1, $service->files($playback));
        $this->assertFalse($service->prepare($playback));
        $this->assertCount(2, $service->files($playback));
        $this->assertTrue($service->prepare($playback));
        $this->assertSame('first', file_get_contents($service->verify($service->files($playback)->first())));
        Storage::disk('backup')->put($service->files($playback)->first()->backup_path, 'altered');
        $this->expectExceptionMessage('La copia de seguridad del video anterior falta o está dañada.');
        $service->prepare($playback);
    }

    public function test_rejects_unconfigured_persistent_backup(): void
    {
        $this->media('a.mp4');
        $playback = $this->playback(['type' => 'z2', 'files' => ['a.mp4']]);
        config(['campaigns.backup_persistent' => false]);
        $this->expectExceptionMessage('Configura un disco local persistente');
        app(CampaignBaselineBackup::class)->prepare($playback);
    }

    public function test_resolves_all_previous_files_before_first_copy(): void
    {
        $this->media('a.mp4');
        $playback = $this->playback(['type' => 'z2', 'files' => ['a.mp4', 'missing.mp4']]);
        try {
            app(CampaignBaselineBackup::class)->prepare($playback);
            $this->fail('A missing original must block preparation.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('missing.mp4', $e->getMessage());
            $this->assertCount(0, app(CampaignBaselineBackup::class)->files($playback));
        }
    }

    public function test_ambiguous_z2_media_is_rejected(): void
    {
        $this->media('a.mp4');
        Media::factory()->video()->create(['file_path' => 'http://cloud.test/fileDownload/Videos/a.mp4']);
        $playback = $this->playback(['type' => 'z2', 'files' => ['a.mp4']]);
        $this->expectExceptionMessage('No se puede identificar y respaldar el video anterior a.mp4');
        app(CampaignBaselineBackup::class)->prepare($playback);
    }

    public function test_model_35_requires_unique_identified_original_for_every_index(): void
    {
        $media = $this->media('a.mp4');
        $playback = $this->playback(['type' => 'wl35', 'count' => 2], 'wl35:dev1');
        Wl35DeviceMedia::create(['device_id' => 'dev1', 'media_id' => $media->id, 'video_index' => 1, 'upload_identity' => (string) Str::uuid()]);
        $this->expectExceptionMessage('índice 2');
        app(CampaignBaselineBackup::class)->prepare($playback);
    }

    public function test_model_35_backups_retain_order_and_upload_identity(): void
    {
        $media = $this->media('a.mp4');
        $identity = (string) Str::uuid();
        $playback = $this->playback(['type' => 'wl35', 'count' => 1], 'wl35:dev1');
        Wl35DeviceMedia::create(['device_id' => 'dev1', 'media_id' => $media->id, 'video_index' => 1, 'upload_identity' => $identity]);
        $service = app(CampaignBaselineBackup::class);
        $this->assertFalse($service->prepare($playback));
        $this->assertTrue($service->prepare($playback));
        $this->assertSame($identity, $service->files($playback)->first()->upload_identity);
        $this->assertSame(1, $service->files($playback)->first()->original_index);
    }

    public function test_complete_backup_survives_catalog_deletion_and_fresh_service_instance(): void
    {
        $media = $this->media('a.mp4', 'original bytes');
        $playback = $this->playback(['type' => 'z2', 'files' => ['a.mp4']]);
        $this->assertFalse(app(CampaignBaselineBackup::class)->prepare($playback));
        $media->forceDelete();
        Storage::disk('local')->delete('a.mp4');
        $service = app()->make(CampaignBaselineBackup::class);
        $this->assertTrue($service->prepare($playback->fresh()));
        $this->assertSame('original bytes', file_get_contents($service->verify($service->files($playback)->first())));
    }

    public function test_missing_original_bytes_never_creates_a_backup_record(): void
    {
        Media::factory()->video()->create(['file_path' => 'missing.mp4']);
        $playback = $this->playback(['type' => 'z2', 'files' => ['missing.mp4']]);
        config(['privatecloud.base_url' => '']);
        try {
            app(CampaignBaselineBackup::class)->prepare($playback);
            $this->fail('The missing source must not be backed up.');
        } catch (RuntimeException $e) {
            $this->assertSame('No se pudo encontrar el archivo de video seleccionado.', $e->getMessage());
            $this->assertCount(0, app(CampaignBaselineBackup::class)->files($playback));
        }
    }

    public function test_empty_baseline_and_unknown_legacy_are_distinct(): void
    {
        $service = app(CampaignBaselineBackup::class);
        $empty = $this->playback(['type' => 'z2', 'files' => []]);
        $this->assertTrue($service->prepare($empty));
        $legacy = $this->playback(['type' => 'legacy'], 'z2:OTHER');
        $this->expectExceptionMessage('No se conoce el contenido anterior');
        $service->prepare($legacy);
    }
}
