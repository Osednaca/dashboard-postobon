<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\MobileApi;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MobileLocationPickerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const PATH = '/api/mobile/v1/establishments/location-picker';

    private const NONCE = 'b55ef9c9-f389-4ac8-bd61-e512ab35bb6e';

    private function authenticate(string $role = 'tecnico', ?array $abilities = null): string
    {
        $this->app['auth']->forgetGuards();
        $token = User::factory()->create(['role' => $role])->createToken('mobile:test', $abilities ?? MobileApi::ABILITIES, now()->addDay())->plainTextToken;
        $this->withToken($token);

        return $token;
    }

    public function test_picker_preserves_valid_location_but_never_embeds_session_credentials(): void
    {
        $token = $this->authenticate();
        config(['services.google_maps.key' => 'public-browser-key']);
        Http::fake();
        $this->get(self::PATH.'?'.http_build_query(['nonce' => self::NONCE, 'address' => '<script>alert(1)</script>', 'latitude' => 4.5, 'longitude' => -74.1]))
            ->assertOk()->assertHeader('Content-Type', 'text/html; charset=utf-8')
            ->assertDontSee('<script>alert(1)</script>', false)->assertDontSee($token, false);
        Http::assertNothingSent();
    }

    public function test_missing_google_configuration_returns_actionable_html_and_does_not_invent_selection(): void
    {
        $this->authenticate();
        config(['services.google_maps.key' => null]);
        $response = $this->get(self::PATH.'?nonce='.self::NONCE)->assertOk();
        $response->assertSee('GOOGLE_MAPS_API_KEY')->assertSee('let selected = null;', false)
            ->assertDontSee('maps.googleapis.com/maps/api/js', false)->assertSee('location-error', false);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_picker_requires_mobile_session_role_ability_nonce_and_valid_paired_coordinates(): void
    {
        $this->getJson(self::PATH.'?nonce='.self::NONCE)->assertUnauthorized();
        $this->authenticate('admin');
        $this->getJson(self::PATH.'?nonce='.self::NONCE)->assertForbidden();
        $this->authenticate('tecnico', ['mobile:session']);
        $this->getJson(self::PATH.'?nonce='.self::NONCE)->assertForbidden();
        $this->authenticate();
        foreach ([[], ['nonce' => 'bad'], ['nonce' => self::NONCE, 'latitude' => 4], ['nonce' => self::NONCE, 'latitude' => 91, 'longitude' => 0], ['nonce' => self::NONCE, 'latitude' => 0, 'longitude' => 181]] as $input) {
            $this->getJson(self::PATH.'?'.http_build_query($input))->assertUnprocessable();
        }
    }
}
