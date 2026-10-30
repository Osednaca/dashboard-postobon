<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Device;
use App\Models\DeviceCampaign;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CampaignAudienceDisplayTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_listing_displays_selected_audiences_and_never_uses_legacy_pivot_for_an_explicit_empty_selection(): void
    {
        Http::preventStrayRequests();
        $this->actingAs(User::factory()->admin()->create());
        $manual = Campaign::factory()->create(['name' => 'Manual', 'audience_mode' => 'manual', 'target_devices' => ['wl35:a'], 'segment_cities' => []]);
        Campaign::factory()->create(['name' => 'Todos', 'audience_mode' => 'all', 'target_devices' => ['wl35:a', 'z2:AABB'], 'segment_cities' => []]);
        Campaign::factory()->create(['name' => 'Segmento', 'audience_mode' => 'segment', 'target_devices' => ['wl35:a'], 'segment_cities' => []]);
        Campaign::factory()->create(['name' => 'Sede', 'audience_mode' => 'establishment', 'target_devices' => ['wl35:a'], 'segment_cities' => []]);
        $legacy = Campaign::factory()->create(['name' => 'Antigua', 'target_devices' => null]);
        $empty = Campaign::factory()->create(['name' => 'Vacia', 'audience_mode' => 'manual', 'target_devices' => []]);
        $device = Device::factory()->create();
        DeviceCampaign::create(['campaign_id' => $legacy->id, 'device_id' => $device->id]);
        DeviceCampaign::create(['campaign_id' => $empty->id, 'device_id' => $device->id]);

        $response = $this->get(route('campaigns.index'))->assertOk();
        $response->assertSee('Selección de dispositivos · 1 dispositivo')
            ->assertSee('Todos · 2 dispositivos')
            ->assertSee('Segmento · 1 dispositivo')
            ->assertSee('Establecimiento · 1 dispositivo')
            ->assertSee('Sin dispositivos asignados')
            ->assertDontSee('Sin segmentación');
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $legacyRow = $xpath->query('//tr[td//div[normalize-space()="Antigua"]]')->item(0);
        $emptyRow = $xpath->query('//tr[td//div[normalize-space()="Vacia"]]')->item(0);
        $this->assertNotNull($legacyRow);
        $this->assertNotNull($emptyRow);
        $this->assertStringContainsString('Selección de dispositivos · 1 dispositivo', $legacyRow->textContent);
        $this->assertStringContainsString('Sin dispositivos asignados', $emptyRow->textContent);
        $this->assertSame(['wl35:a'], $manual->fresh()->target_devices);
        $this->assertSame([], $empty->fresh()->target_devices);
        Http::assertNothingSent();
    }
}
