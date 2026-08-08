<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaDeleteTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

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

        $response = $this->actingAs($this->user)
            ->delete(route('media.destroy', $media));

        $response->assertRedirect(route('media.index'));
        $response->assertSessionHas('success');

        $this->assertSoftDeleted('media', ['id' => $media->id]);
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
}
