<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\User;
use App\Services\MediaService;
use App\Services\Z2\Z2VideoService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaUploadProgressTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_json_upload_returns_navigation_and_preserves_success_flash(): void
    {
        $media = Media::create([
            'name' => 'cloud.mp4', 'original_name' => 'cloud.mp4', 'file_path' => 'cloud.mp4',
            'mime_type' => 'video/mp4', 'size' => 12,
        ]);
        $this->mock(Z2VideoService::class)->shouldReceive('uploadVideo')->once()->andReturn($media);
        $user = User::factory()->create(['role' => 'superadmin']);

        $response = $this->actingAs($user)->postJson(route('media.store'), [
            'file' => UploadedFile::fake()->create('video.mp4', 1, 'video/mp4'),
        ]);

        $response->assertOk()->assertJsonPath('redirect', route('media.index'))->assertSessionHas('success', 'Medio creado exitosamente.');
        $this->assertSame('video.mp4', $media->fresh()->original_name);
    }

    public function test_standard_form_keeps_its_redirect_and_local_fallback(): void
    {
        $media = Media::create([
            'name' => 'local.mp4', 'original_name' => 'local.mp4', 'file_path' => 'media/local.mp4',
            'mime_type' => 'video/mp4', 'size' => 12,
        ]);
        $this->mock(Z2VideoService::class)->shouldReceive('uploadVideo')->once()->andReturn(null);
        $this->mock(MediaService::class)->shouldReceive('create')->once()->andReturn($media);
        $user = User::factory()->create(['role' => 'superadmin']);

        $this->actingAs($user)->post(route('media.store'), [
            'file' => UploadedFile::fake()->create('video.mp4', 1, 'video/mp4'),
        ])->assertRedirect(route('media.index'))->assertSessionHas('success', 'Medio creado exitosamente.');
    }

    public function test_json_upload_reports_validation_errors(): void
    {
        $user = User::factory()->create(['role' => 'superadmin']);
        $this->actingAs($user)->postJson(route('media.store'), [])->assertUnprocessable()->assertJsonValidationErrors('file');
    }

    public function test_json_upload_reports_processing_errors(): void
    {
        $user = User::factory()->create(['role' => 'superadmin']);
        $this->mock(Z2VideoService::class)->shouldReceive('uploadVideo')->once()->andReturn(null);
        $this->mock(MediaService::class)->shouldReceive('create')->once()->andThrow(new \RuntimeException('failure'));
        $this->actingAs($user)->postJson(route('media.store'), [
            'file' => UploadedFile::fake()->create('video.mp4', 1, 'video/mp4'),
        ])->assertStatus(500)->assertJsonPath('message', 'Ocurrió un error al crear el medio. Por favor intente nuevamente.');
    }
}
