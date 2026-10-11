<?php

namespace Tests\Feature;

use App\Models\BusinessType;
use App\Models\Establishment;
use App\Models\User;
use App\Models\Wl35DeviceProfile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DeviceLocationMapTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['unifiedfleet.base_url' => 'http://gateway.test', 'unifiedfleet.fallback_base_url' => '']);
        Http::preventStrayRequests();
    }

    private function coordinates(mixed $latitude = null, mixed $longitude = null): object
    {
        return (object) ['id' => 1, 'latitude' => $latitude, 'longitude' => $longitude, 'address' => null];
    }

    public static function coordinateCases(): array
    {
        return [
            'establishment first' => [[4.6, -74.1], [5, -75], [6, -76], '4.6,-74.1'],
            'legacy profile fallback' => [[null, null], [5, -75], [6, -76], '5,-75'],
            'legacy location fallback' => [[null, null], [null, null], [6, -76], '6,-76'],
            'zero coordinates' => [[0, 0], [5, -75], [6, -76], '0,0'],
            'partial establishment skips whole pair' => [[4.6, null], [5, -75], [6, -76], '5,-75'],
            'out of range falls back' => [[91, -74.1], [5, 181], [6, -76], '6,-76'],
            'invalid numeric values fall back' => [['invalid', -74.1], [INF, -75], [6, -76], '6,-76'],
            'never combine incomplete pairs' => [[4.6, null], [null, -75], [null, null], null],
        ];
    }

    #[DataProvider('coordinateCases')]
    public function test_complete_coordinate_pair_priority_and_validation(array $establishment, array $legacy, array $location, ?string $expected): void
    {
        $profile = $this->coordinates(...$legacy);
        $profile->establishmentProfile = $this->coordinates(...$establishment);
        $profile->location = $this->coordinates(...$location);

        $html = Blade::render('<x-device-location-map :profile="$profile" device-name="Equipo de prueba" />', compact('profile'));

        if ($expected === null) {
            $this->assertStringNotContainsString('<iframe', $html);
            $this->assertStringContainsString('Sin coordenadas registradas', $html);
            $this->assertStringContainsString(route('establishments.edit', 1), $html);
        } else {
            $this->assertStringContainsString('https://www.google.com/maps?q='.$expected.'&amp;output=embed', $html);
            $this->assertStringContainsString('Mapa de Equipo de prueba', $html);
            $this->assertStringNotContainsString('Sin coordenadas registradas', $html);
        }
        Http::assertNothingSent();
    }

    public function test_device_without_saved_profile_has_a_useful_missing_location_state(): void
    {
        $html = Blade::render('<x-device-location-map />');

        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertStringContainsString('Selecciona un establecimiento', $html);
    }

    public function test_offline_wl35_detail_keeps_establishment_map(): void
    {
        $establishment = Establishment::create([
            'business_type_id' => BusinessType::firstOrCreate(['name' => 'Tienda'])->id,
            'name' => 'Tienda Centro', 'address' => 'Calle 1', 'latitude' => 4.6, 'longitude' => -74.1,
        ]);
        Wl35DeviceProfile::create(['device_id' => 'fan-a', 'name' => 'Entrada', 'establishment_id' => $establishment->id]);
        Http::fake(['gateway.test/*' => Http::response([], 503)]);

        $this->actingAs(User::factory()->admin()->create())->get(route('devices.wl35.show', 'fan-a'))
            ->assertOk()->assertSee('Fuera de línea')->assertSee('Mapa de Entrada')
            ->assertSee('https://www.google.com/maps?q=4.6,-74.1&amp;output=embed', false);
        Http::assertNotSent(fn ($request) => $request->method() !== 'GET');
    }

    public function test_dashboard_map_has_its_own_stacking_context_below_mobile_navigation(): void
    {
        $html = view('dashboard.index', ['data' => []])->render();

        $this->assertStringContainsString('id="device-map" class="relative isolate z-0 ', $html);
        $this->assertStringContainsString('left-0 z-50 w-64', $html);
        $this->assertStringContainsString('fixed inset-0 z-40', $html);
        Http::assertNothingSent();
    }
}
