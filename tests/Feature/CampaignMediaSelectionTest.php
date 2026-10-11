<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Js;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CampaignMediaSelectionTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_create_and_edit_share_square_authenticated_previews_and_restore_saved_order(): void
    {
        $video = Media::factory()->video()->create(['name' => 'Video principal', 'duration' => 3661]);
        $image = Media::factory()->image()->create(['name' => 'Imagen secundaria']);
        $campaign = Campaign::factory()->create();
        $campaign->media()->attach([$image->id => ['order' => 1], $video->id => ['order' => 2]]);

        foreach (['campaigns.create', 'campaigns.edit'] as $route) {
            $response = $this->get(route($route, $route === 'campaigns.edit' ? $campaign : []))
                ->assertOk()->assertSee('data-campaign-media-selector', false)
                ->assertSee('Agregar a la selección')->assertSee('Subir')->assertSee('Bajar')->assertSee('Quitar')
                ->assertSee('aspect-square')->assertSee('1:01:01')->assertSee('media_ids[]', false)
                ->assertDontSee('cursor-move')->assertDontSee($video->file_path);
            $this->assertSame(2, substr_count($response->getContent(), 'x-data="mediaPreview('));
            $response->assertSee(Js::from(route('media.content', $video))->toHtml(), false)
                ->assertSee(Js::from(route('media.content', $image))->toHtml(), false);
            $this->assertMatchesRegularExpression('/<video\b[^>]*\bcontrols\b/', $response->getContent());
        }
        $this->get(route('campaigns.edit', $campaign))
            ->assertSee(Js::from([$image->id, $video->id])->toHtml(), false);
        Http::assertNothingSent();
    }

    public function test_web_create_and_edit_save_normalized_selection_order_without_publishing(): void
    {
        [$first, $second] = Media::factory()->video()->count(2)->create()->all();
        $this->post(route('campaigns.store'), [
            'name' => 'Campaña visual', 'start_date' => '2026-10-10', 'is_permanent' => 1,
            'media_ids' => ['second' => (string) $second->id, 'first' => (string) $first->id],
        ])->assertRedirect(route('campaigns.index'))->assertSessionHasNoErrors();
        $campaign = Campaign::sole();
        $this->assertSame([$second->id, $first->id], $campaign->media->modelKeys());
        $this->assertSame([1, 2], $campaign->media->pluck('pivot.order')->all());
        $campaign->update(['status' => 'active']);

        $this->put(route('campaigns.update', $campaign), ['media_ids' => [$first->id, $second->id]])
            ->assertSessionHasNoErrors();
        $this->assertSame([$first->id, $second->id], $campaign->fresh()->media->modelKeys());
        $this->assertSame('active', $campaign->fresh()->status);
        $this->assertDatabaseCount('campaign_media', 2);
        Http::assertNothingSent();
    }

    public function test_api_omission_preserves_relations_legacy_csv_changes_order_and_explicit_empty_clears(): void
    {
        [$first, $second] = Media::factory()->video()->count(2)->create()->all();
        $campaign = Campaign::factory()->create();
        $campaign->media()->attach($first, ['order' => 1]);
        $this->patchJson(route('api.campaigns.update', $campaign), ['name' => 'Nombre editado'])->assertOk();
        $this->assertSame([$first->id], $campaign->fresh()->media->modelKeys());

        $this->patchJson(route('api.campaigns.update', $campaign), ['videos' => " {$second->id}, {$first->id} "])->assertOk();
        $this->assertSame([$second->id, $first->id], $campaign->fresh()->media->modelKeys());
        $this->patchJson(route('api.campaigns.update', $campaign), ['media_ids' => []])->assertOk();
        $this->assertCount(0, $campaign->fresh()->media);

        $campaign->media()->attach($first, ['order' => 1]);
        $this->put(route('campaigns.update', $campaign), ['media_selection_present' => 1])->assertSessionHasNoErrors();
        $this->assertCount(0, $campaign->fresh()->media);
        Http::assertNothingSent();
    }

    public static function invalidSelection(): array
    {
        return [
            'duplicate' => [[1, '1'], 'media_ids.0'],
            'malformed id' => [['1evil'], 'media_ids.0'],
            'missing id' => [[99999], 'media_ids.0'],
            'scalar' => ['1', 'media_ids'],
            'nested id' => [[['id' => 1]], 'media_ids.0'],
        ];
    }

    #[DataProvider('invalidSelection')]
    public function test_invalid_selection_is_rejected_before_changing_fields_or_relations(mixed $ids, string $field): void
    {
        $media = Media::factory()->create(['id' => 1]);
        $campaign = Campaign::factory()->create(['name' => 'Original']);
        $campaign->media()->attach($media, ['order' => 1]);
        $this->patchJson(route('api.campaigns.update', $campaign), ['name' => 'No guardar', 'media_ids' => $ids])
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertSame('Original', $campaign->fresh()->name);
        $this->assertSame([$media->id], $campaign->fresh()->media->modelKeys());
        Http::assertNothingSent();
    }

    public function test_deleted_media_is_rejected_and_submitted_selection_restored_after_form_error(): void
    {
        $valid = Media::factory()->video()->create();
        $deleted = Media::factory()->video()->create();
        $deleted->delete();
        $campaign = Campaign::factory()->create(['name' => 'Original']);
        $campaign->media()->attach($valid, ['order' => 1]);
        $ids = [(string) $deleted->id, (string) $valid->id];

        $this->from(route('campaigns.edit', $campaign))
            ->put(route('campaigns.update', $campaign), ['name' => 'Intento', 'media_ids' => $ids])
            ->assertRedirect(route('campaigns.edit', $campaign))->assertSessionHasErrors('media_ids.0');
        $this->get(route('campaigns.edit', $campaign))->assertOk()
            ->assertSee('ya no está disponible')->assertSee('value="Intento"', false)
            ->assertSee(Js::from($ids)->toHtml(), false)->assertDontSee($deleted->name);
        $this->assertSame('Original', $campaign->fresh()->name);
        $this->assertSame([$valid->id], $campaign->fresh()->media->modelKeys());
        Http::assertNothingSent();
    }

    public function test_media_write_failure_rolls_back_both_campaign_update_and_previous_selection(): void
    {
        [$first, $second] = Media::factory()->video()->count(2)->create()->all();
        $campaign = Campaign::factory()->create(['name' => 'Original']);
        $campaign->media()->attach($first, ['order' => 1]);
        DB::unprepared("CREATE TRIGGER fail_campaign_media BEFORE INSERT ON campaign_media BEGIN SELECT RAISE(ABORT, 'fixture failure'); END");
        try {
            $this->from(route('campaigns.edit', $campaign))
                ->put(route('campaigns.update', $campaign), ['name' => 'No guardar', 'media_ids' => [$second->id]])
                ->assertRedirect(route('campaigns.edit', $campaign))->assertSessionHas('error');
            $this->assertSame('Original', $campaign->fresh()->name);
            $this->assertSame([$first->id], $campaign->fresh()->media->modelKeys());
            $this->assertSame([1], $campaign->fresh()->media->pluck('pivot.order')->all());
        } finally {
            DB::unprepared('DROP TRIGGER fail_campaign_media');
        }
        Http::assertNothingSent();
    }
}
