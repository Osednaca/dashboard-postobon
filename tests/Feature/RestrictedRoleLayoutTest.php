<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use App\Models\Wl35DeviceProfile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RestrictedRoleLayoutTest extends TestCase
{
    use LazilyRefreshDatabase;

    public static function readerRoles(): array
    {
        return [['tecnico', true], ['campanas', false]];
    }

    #[DataProvider('readerRoles')]
    public function test_device_list_preserves_information_without_controls(string $role, bool $assigns): void
    {
        Http::preventStrayRequests();
        $this->actingAs(User::factory()->create(['role' => $role]));
        $device = Device::factory()->create(['name' => 'Equipo visible', 'mac_address' => 'AA']);
        $view = $this->view('devices.index', [
            'devices' => new LengthAwarePaginator(collect([$device]), 1, 15),
            'wl35Devices' => collect([['id' => 'known-35', 'name' => 'Segundo equipo', 'online' => true,
                'connected' => true, 'power' => false, 'bluetooth' => false]]),
            'fleetZ2Devices' => collect(), 'fleetMedia' => collect(), 'establishments' => collect(),
            'gatewayError' => null, 'gatewayConfigured' => true,
        ]);
        $view->assertSee('Equipo visible')->assertSee('Segundo equipo')->assertSee(route('devices.show', $device), false)
            ->assertDontSee('href="'.route('devices.create').'"', false)->assertDontSee('Subir video');
        $document = new \DOMDocument;
        @$document->loadHTML(mb_convert_encoding((string) $view, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new \DOMXPath($document);
        $this->assertSame(2, $xpath->query('//tr[@data-device-row]/td[contains(@class,"device-cell-name")]//a[contains(@class,"device-name") and .//span[contains(@class,"device-detail-hint")]]')->length);
        $this->assertSame(0, $xpath->query('//*[@data-fleet-selector or @data-device-menu]')->length);
        $this->assertSame(0, $xpath->query('//form[contains(@action,"/fleet/") or contains(@action,"/devices/")]')->length);
        $this->assertSame(0, $xpath->query('//a[contains(@href,"/devices/") and contains(@href,"/edit")]')->length);
        Http::assertNothingSent();
    }

    #[DataProvider('readerRoles')]
    public function test_device_details_expose_only_information_and_permitted_association(string $role, bool $assigns): void
    {
        config(['unifiedfleet.base_url' => 'http://gateway.test', 'unifiedfleet.token' => 'test-token',
            'unifiedfleet.fallback_base_url' => '']);
        Http::preventStrayRequests();
        Http::fake(['gateway.test/api/fleet' => Http::response(['devices' => [[
            'type' => 'wl35', 'id' => 'known-35', 'name' => 'Equipo 35', 'video_count' => 0,
        ]]])]);
        $this->actingAs(User::factory()->create(['role' => $role]));
        $device = Device::factory()->create(['mac_address' => '']);
        Wl35DeviceProfile::create(['device_id' => 'known-35', 'name' => 'Equipo 35']);
        $z2 = $this->get(route('devices.show', $device))->assertOk()
            ->assertDontSee(route('devices.edit', $device), false)
            ->assertDontSee(route('devices.power-on', $device), false)
            ->assertDontSee(route('devices.assign-media', $device), false)
            ->assertDontSee(route('devices.set-volume', $device), false)
            ->assertDontSee('name="name"', false)->assertDontSee('name="mac_address"', false);
        $wl35 = $this->get(route('devices.wl35.show', 'known-35'))->assertOk()
            ->assertDontSee('action="'.route('devices.wl35.update', 'known-35').'"', false)
            ->assertDontSee(route('devices.wl35.volume', 'known-35'), false)
            ->assertDontSee(route('devices.wl35.videos.destroy', 'known-35'), false)
            ->assertDontSee(route('fleet.command'), false)->assertDontSee(route('fleet.upload'), false)
            ->assertDontSee('name="name"', false)->assertDontSee('name="notes"', false);
        if ($role === 'tecnico') {
            $z2->assertDontSee('<video', false)->assertDontSee('x-data="previewPlayer', false);
            $wl35->assertDontSee('<video', false)->assertDontSee('x-data="previewPlayer', false);
        }
        if ($assigns) {
            $z2->assertSee(route('devices.assign-establishment', $device), false)->assertSee('name="establishment_id"', false);
            $wl35->assertSee(route('devices.wl35.assign-establishment', 'known-35'), false)->assertSee('name="establishment_id"', false);
        } else {
            $z2->assertDontSee(route('devices.assign-establishment', $device), false);
            $wl35->assertDontSee(route('devices.wl35.assign-establishment', 'known-35'), false);
        }
        Http::assertNotSent(fn ($request) => $request->method() !== 'GET');
    }

    public function test_navigation_hides_removed_modules_and_campaigns_groups_link_opens_list(): void
    {
        foreach (['admin', 'tecnico', 'campanas'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            foreach (['layouts.app', 'components.app-layout'] as $layout) {
                $html = view($layout, ['slot' => 'Contenido'])->render();
                foreach (['users.index', 'audit.index', 'notifications.index', 'analytics.index'] as $route) {
                    $this->assertStringNotContainsString('href="'.route($route).'"', $html);
                }
                if ($role === 'tecnico') {
                    $this->assertStringContainsString('href="'.route('establishments.index').'"', $html);
                    $this->assertStringContainsString('href="'.route('devices.index').'"', $html);
                    foreach (['dashboard.index', 'campaigns.index', 'media.index', 'schedules.index', 'groups.index', 'instant-play.index'] as $route) {
                        $this->assertStringNotContainsString('href="'.route($route).'"', $html);
                    }
                } else {
                    $this->assertStringNotContainsString('href="'.route('establishments.index').'"', $html);
                    $this->assertStringContainsString('href="'.route('campaigns.index').'"', $html);
                    if ($role === 'campanas') {
                        $this->assertStringContainsString('href="'.route('groups.index').'"', $html);
                    } else {
                        $this->assertStringNotContainsString('href="'.route('devices.index').'"', $html);
                    }
                }
            }
        }
    }
}
