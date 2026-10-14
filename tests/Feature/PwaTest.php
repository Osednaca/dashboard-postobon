<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PwaTest extends TestCase
{
    public function test_manifest_starts_at_role_entry_with_standalone_brand_icons(): void
    {
        $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('/', $manifest['start_url']);
        $this->assertSame('/', $manifest['scope']);
        $this->assertSame('/', $manifest['id']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertNotEmpty($manifest['name']);
        $this->assertSame(['192x192', '512x512'], array_column($manifest['icons'], 'sizes'));
        foreach ($manifest['icons'] as $icon) {
            $size = getimagesize(public_path(ltrim($icon['src'], '/')));
            $this->assertSame($icon['sizes'], "{$size[0]}x{$size[1]}");
            $this->assertSame('image/png', $size['mime']);
        }
    }

    public function test_installed_app_launch_reaches_the_permitted_role_landing(): void
    {
        Http::preventStrayRequests();
        $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);
        foreach (['tecnico' => 'establishments.index', 'admin' => 'dashboard.index'] as $role => $landing) {
            $this->actingAs(User::factory()->make(['id' => 1, 'role' => $role]));
            $entry = $this->get($manifest['start_url'])->assertRedirect(route('login'));
            $this->get($entry->headers->get('Location'))->assertRedirect(route($landing));
        }
        Http::assertNothingSent();
    }

    public function test_login_and_panel_link_same_origin_manifest_and_install_button_is_initially_hidden(): void
    {
        Http::preventStrayRequests();
        $this->withoutVite();
        $this->get('/login')->assertOk()->assertSee('href="/manifest.webmanifest"', false)
            ->assertSee('name="theme-color"', false)->assertSee('href="/pwa/icon-192.png"', false);
        $this->actingAs(User::factory()->superadmin()->make(['id' => 1]));
        $panel = view('layouts.app', ['title' => 'Panel'])->render();
        $this->assertStringContainsString('href="/manifest.webmanifest"', $panel);
        $this->assertStringContainsString('data-pwa-install hidden', $panel);
        $this->assertStringContainsString('Instalar aplicación', $panel);
        Http::assertNothingSent();
    }
}
