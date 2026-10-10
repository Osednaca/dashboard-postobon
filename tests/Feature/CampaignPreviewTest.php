<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Js;
use Tests\TestCase;

class CampaignPreviewTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    public function test_campaign_previews_configured_media_without_claiming_live_playback(): void
    {
        $user = User::factory()->admin()->create();
        $campaign = Campaign::create(['name' => 'Campaña tienda', 'status' => 'draft', 'priority' => 1, 'created_by' => $user->id]);
        $video = Media::create([
            'name' => 'Video campaña', 'original_name' => 'campaign.mp4',
            'file_path' => 'http://cloud.test/fileDownload/Videos/campaign.mp4',
            'mime_type' => 'video/mp4', 'size' => 100, 'duration' => 3661,
        ]);
        $image = Media::create([
            'name' => 'Imagen campaña', 'original_name' => 'campaign.png',
            'file_path' => 'media/campaign.png', 'mime_type' => 'image/png', 'size' => 100,
        ]);
        $campaign->media()->attach([$video->id => ['order' => 1], $image->id => ['order' => 2]]);

        $response = $this->actingAs($user)
            ->get(route('campaigns.show', $campaign))->assertOk()
            ->assertSee('contenido configurado para esta campaña')
            ->assertSee('La reproducción actual se consulta en el detalle de cada dispositivo.')
            ->assertSee('preload="metadata"', false)
            ->assertSee('alt="Imagen campaña"', false)
            ->assertSee('1:01:01')->assertSee('mediaDurations.label')
            ->assertSee('Orden #1')->assertSee('Orden #2')
            ->assertDontSee('http://cloud.test/fileDownload', false)
            ->assertDontSee('devicePreviews(');
        foreach ([$video, $image] as $media) {
            $response->assertSee(Js::from(route('media.content', $media))->toHtml(), false);
        }
        $this->assertSame(2, substr_count($response->getContent(), 'x-data="mediaPreview('));
        $this->assertMatchesRegularExpression('/<video\b[^>]*\bcontrols\b/', $response->getContent());
        Http::assertNothingSent();
    }

    public function test_empty_campaign_shows_no_preview_players(): void
    {
        $user = User::factory()->admin()->create();
        $campaign = Campaign::create(['name' => 'Campaña vacía', 'status' => 'draft', 'priority' => 1, 'created_by' => $user->id]);

        $this->actingAs($user)->get(route('campaigns.show', $campaign))
            ->assertOk()->assertSee('No hay videos asignados a esta campaña')
            ->assertDontSee('mediaPreview(')->assertDontSee('devicePreviews(');
        Http::assertNothingSent();
    }
}
