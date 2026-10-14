<?php

namespace Tests\Feature;

use App\Models\BusinessType;
use App\Models\Campaign;
use App\Models\Device;
use App\Models\Establishment;
use App\Models\User;
use App\Models\Wl35DeviceProfile;
use App\Services\CampaignTargetCatalog;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeviceModelDisplayTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->actingAs(User::factory()->superadmin()->create());
    }

    public function test_establishments_display_model_labels_without_renaming_devices_or_routes(): void
    {
        $place = Establishment::create([
            'business_type_id' => BusinessType::firstOrCreate(['name' => 'Tienda'])->id,
            'name' => 'Establecimiento visible', 'address' => 'Calle 1',
        ]);
        $device = Device::factory()->create([
            'name' => 'Z2 recepción', 'establishment_id' => $place->id,
        ]);
        $profile = Wl35DeviceProfile::create([
            'device_id' => 'fan-wl35', 'name' => 'WL35 entrada', 'establishment_id' => $place->id,
        ]);

        $this->get(route('establishments.index'))->assertOk()
            ->assertSee('para 42 y 35.')->assertSee('1 42 · 1 35');
        $this->get(route('establishments.show', $place))->assertOk()
            ->assertSee('Dispositivos 42')->assertSee('Dispositivos 35')->assertSee('Registrar 42')
            ->assertSee('Z2 recepción')->assertSee('WL35 entrada')
            ->assertSee(route('devices.show', $device), false)
            ->assertSee(route('devices.wl35.show', $profile->device_id), false);
        Http::assertNothingSent();
    }

    public function test_campaign_recipient_labels_preserve_catalog_protocols_and_user_names(): void
    {
        Device::factory()->create(['name' => 'Z2 recepción', 'mac_address' => 'AA:BB']);
        Wl35DeviceProfile::create(['device_id' => 'fan-wl35', 'name' => 'WL35 entrada']);
        $keys = ['z2:AABB', 'wl35:fan-wl35'];
        $campaign = Campaign::factory()->create(['target_devices' => $keys]);

        $this->view('components.campaign-recipients', compact('campaign'))
            ->assertSee('42 · Sin ciudad')->assertSee('35 · Sin ciudad')
            ->assertSee('Z2 recepción')->assertSee('WL35 entrada');
        $catalog = app(CampaignTargetCatalog::class)->forCampaign($campaign);
        $this->assertSame($keys, $catalog->pluck('key')->all());
        $this->assertSame(['z2', 'wl35'], $catalog->pluck('type')->all());
        $this->assertSame($keys, $campaign->fresh()->target_devices);
        Http::assertNothingSent();
    }

    public function test_reproduction_labels_preserve_selection_values_and_status_phase_keys(): void
    {
        $devicesWithPlaying = collect([
            ['key' => 'z2:AABB', 'type' => 'z2', 'name' => 'Z2 recepción', 'identifier' => 'AA:BB',
                'online' => true, 'status_label' => 'En línea', 'establishment' => null, 'group' => null,
                'location' => null, 'current_playing' => 'WL35-original.mp4', 'video_count' => 1],
            ['key' => 'wl35:fan-wl35', 'type' => 'wl35', 'name' => 'WL35 entrada', 'identifier' => 'fan-wl35',
                'online' => true, 'status_label' => 'En línea', 'establishment' => null, 'group' => null,
                'location' => null, 'current_playing' => 'Video 1', 'video_count' => 1],
        ]);
        $this->view('instant-play.index', [
            'devicesWithPlaying' => $devicesWithPlaying, 'allMedia' => collect(),
            'campaigns' => collect(), 'gatewayError' => null,
        ])->assertSee('ventiladores 42 y 35')->assertSee('Z2 recepción')->assertSee('WL35 entrada')
            ->assertSee('WL35-original.mp4')
            ->assertSee('value="z2:AABB"', false)->assertSee('value="wl35:fan-wl35"', false)
            ->assertSee("sending_z2: 'Enviando la orden a 42'", false);
        Http::assertNothingSent();
    }

    public function test_campaign_device_links_follow_role_permissions_without_marking_known_devices_missing(): void
    {
        $device = Device::factory()->create(['name' => 'Equipo disponible', 'mac_address' => 'AABB']);
        Wl35DeviceProfile::create(['device_id' => 'fan-a', 'name' => 'Segundo disponible']);
        $campaign = Campaign::factory()->create(['target_devices' => ['z2:AABB', 'wl35:fan-a', 'wl35:missing']]);
        $urls = [route('devices.show', $device), route('devices.wl35.show', 'fan-a')];
        foreach (['admin', 'campanas', 'superadmin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $view = $this->view('components.campaign-recipients', compact('campaign'));
            foreach ($urls as $url) {
                if ($role === 'admin') {
                    $view->assertDontSee('href="'.$url.'"', false);
                } else {
                    $view->assertSee('href="'.$url.'"', false);
                }
            }
            $view->assertSee('Equipo disponible')->assertSee('Segundo disponible')
                ->assertSee('class="font-medium text-danger">Dispositivo no disponible', false);
            if ($role === 'admin') {
                $view->assertSee('class="font-medium text-text">Equipo disponible', false);
            }
        }
        Http::assertNothingSent();
    }
}
