<?php

namespace Tests\Feature;

use App\Models\Media;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SquarePreviewTest extends TestCase
{
    public function test_shared_device_and_library_players_keep_square_bounds_without_cropping(): void
    {
        Http::preventStrayRequests();
        $media = new Media(['name' => 'Muestra', 'mime_type' => 'video/mp4']);
        $media->id = 42;

        foreach (['z2:AABB', 'wl35:fan-a'] as $key) {
            $html = Blade::render('<x-device-previews :device-key="$key" />', compact('key'));
            $this->assertStringContainsString('aspect-square w-full max-w-[28rem]', $html);
            $this->assertStringNotContainsString('max-h-', $html);
            $this->assertStringContainsString('object-contain', $html);
            $this->assertStringNotContainsString('autoplay', $html);
        }
        $html = Blade::render('<x-media-preview :media="$media" detail />', compact('media'));
        $this->assertStringContainsString('aspect-square w-full max-w-[28rem]', $html);
        $this->assertStringContainsString('object-contain', $html);
        $this->assertStringNotContainsString('aspect-video', $html);
        $compact = Blade::render('<x-media-preview :media="$media" compact />', compact('media'));
        $this->assertStringContainsString('h-10 w-10', $compact);
        Http::assertNothingSent();
    }
}
