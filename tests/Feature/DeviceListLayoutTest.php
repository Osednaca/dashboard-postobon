<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeviceListLayoutTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_list_preserves_device_commands_and_reports_only_known_content(): void
    {
        Http::preventStrayRequests();
        $this->actingAs(User::factory()->admin()->create());
        $device = Device::factory()->create([
            'name' => 'Z2 recepción', 'mac_address' => 'AA:BB', 'status' => 'offline',
            'power_status' => 'off', 'bluetooth_status' => 'on',
        ]);
        $data = [
            'devices' => new LengthAwarePaginator(collect([$device]), 21, 15),
            'wl35Devices' => collect([
                ['id' => 'wl35-a', 'name' => 'WL35 entrada', 'online' => true, 'connected' => true, 'power' => false, 'bluetooth' => false, 'current_video' => 3],
                ['id' => 'wl35-b', 'name' => 'WL35 desconectado', 'online' => true, 'connected' => false, 'power' => true],
            ]),
            'fleetZ2Devices' => collect(['AABB' => ['online' => true, 'power' => true, 'current_video' => 'real-file.mp4']]),
            'fleetMedia' => collect(), 'establishments' => collect(), 'gatewayError' => null, 'gatewayConfigured' => true,
        ];
        $view = $this->view('devices.index', $data);
        $view->assertSee('Dispositivos')->assertSee('21 Z2 registrados y 2 WL35')
            ->assertSee('Resumen de los equipos visibles en esta página')->assertSee('real-file.mp4')
            ->assertSee('Apagar Bluetooth')->assertSee('Encender Bluetooth')->assertSee('Formatear tarjeta SD')
            ->assertSee('Esta acción no se puede deshacer.')->assertSee('Administrar Z2')->assertSee('Subir video')
            ->assertDontSee('Emparejar Bluetooth')->assertDontSee('Ajustar brillo')->assertDontSee('Mantenimiento')
            ->assertSee('x-for="key in playbackTargets"', false)->assertSee('x-for="key in selectedFleetKeys"', false)
            ->assertSee('data-device-row="z2:AABB"', false)->assertSee('data-device-row="wl35:wl35-a"', false);
        foreach (['devices.power-on', 'devices.power-off', 'devices.edit', 'devices.show'] as $route) {
            $view->assertSee(route($route, $device), false);
        }
        $html = (string) $view;
        $this->assertStringContainsString('bluetooth-off', $html);
        $document = new \DOMDocument;
        @$document->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new \DOMXPath($document);
        $this->assertSame(7, $xpath->query('//table[contains(@class,"device-list-table")]/thead/tr/th')->length);
        $this->assertSame(3, $xpath->query('//input[@data-fleet-selector]')->length);
        $this->assertSame(3, $xpath->query('//tr[@data-device-row]/td[contains(@class,"device-cell-status")]//span[@role="status"]')->length);
        $this->assertSame(2, $xpath->query('//form[input[@name="command" and @value="bluetooth"] and input[@name="targets[]" and @value="wl35:wl35-a"] and input[@name="value"]]')->length);
        $this->assertStringContainsString('operatingLabels[rows[0].status]', $html);
        $this->assertStringContainsString('applySnapshot($event.detail)', $html);
        $this->assertStringContainsString('sendDeviceCommand($event.currentTarget, $event.submitter)', $html);
        $this->assertSame(1, $xpath->query('//div[@hidden and contains(@x-data,"devicePreviews")]')->length);
        $this->assertSame(3, $xpath->query('//section[@data-device-menu and @role="dialog" and @aria-modal="true"]')->length);
        Http::assertNothingSent();
    }

    public function test_empty_list_and_gateway_error_keep_actions_and_filter_recovery_available(): void
    {
        Http::preventStrayRequests();
        $this->actingAs(User::factory()->admin()->create());
        $this->view('devices.index', [
            'devices' => new LengthAwarePaginator(collect(), 0, 15), 'wl35Devices' => collect(),
            'fleetZ2Devices' => collect(), 'fleetMedia' => collect(), 'establishments' => collect(),
            'gatewayError' => 'Gateway no disponible', 'gatewayConfigured' => true,
        ])->assertSee('No hay dispositivos')->assertSee('Nuevo dispositivo Z2')->assertSee('Gateway no disponible');
        Http::assertNothingSent();
    }
}
