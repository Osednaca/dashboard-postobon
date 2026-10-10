<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Device;
use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DeviceDetailLayoutTest extends TestCase
{
    use LazilyRefreshDatabase;

    public static function contentStates(): array
    {
        return ['empty' => [false], 'populated' => [true]];
    }

    #[DataProvider('contentStates')]
    public function test_detail_orders_content_then_administration_and_preserves_controls(bool $populated): void
    {
        config(['privatecloud.base_url' => 'http://cloud.test']);
        Http::preventStrayRequests();
        Http::fake(['cloud.test/api/devices/AABB' => Http::response(['result' => 0, 'device' => [
            'playlist' => $populated ? ['campaign.mp4'] : [], 'volume' => 40,
            'btSwitch' => 1, 'diagnostic_marker' => 'RAW-CLOUD-MARKER',
        ]])]);
        $user = User::factory()->admin()->create();
        $device = Device::factory()->create(['mac_address' => 'AABB', 'rpm' => 1200]);
        if ($populated) {
            $campaign = Campaign::create(['name' => 'Campaña local', 'status' => 'active', 'priority' => 1, 'created_by' => $user->id]);
            $device->deviceCampaigns()->create(['campaign_id' => $campaign->id, 'status' => 'active', 'started_at' => now()]);
            Media::create(['name' => 'Video local', 'original_name' => 'campaign.mp4', 'file_path' => 'campaign.mp4',
                'mime_type' => 'video/mp4', 'duration' => 28, 'size' => 100]);
        }

        $response = $this->actingAs($user)->get(route('devices.show', $device))->assertOk()
            ->assertSeeInOrder(['Contenido actual', 'Campañas Asignadas', 'Videos del Dispositivo',
                'Asignar Video Directamente', 'Información del Dispositivo'])
            ->assertDontSee('Historial de Heartbeats')->assertDontSee('RPM')->assertDontSee('Información de Z2 Cloud')
            ->assertDontSee('RAW-CLOUD-MARKER')->assertSee('Control de Volumen de Audio')->assertSee('Bluetooth')
            ->assertSee('Formatear SD')->assertSee('Desvincular');
        foreach (['devices.power-on', 'devices.power-off', 'devices.set-volume',
            'devices.bluetooth-on', 'devices.bluetooth-off', 'devices.format-sd', 'devices.unbind', 'devices.remove-media'] as $route) {
            $response->assertSee(route($route, $device), false);
        }
        if ($populated) {
            $response->assertSee('Campaña local')->assertSee('Video local')->assertSee('Quitar');
        } else {
            $response->assertSee('No hay campañas asignadas')->assertSee('El dispositivo no reporta videos.');
        }
        Http::assertSentCount(3);
        Http::assertNotSent(fn (Request $request) => $request->method() !== 'GET');
    }
}
