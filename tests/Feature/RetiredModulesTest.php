<?php

namespace Tests\Feature;

use App\DTOs\DeviceDTO;
use App\Events\SubscriptionExpiring;
use App\Jobs\CheckExpiringSubscriptionsJob;
use App\Listeners\SendSubscriptionAlert;
use App\Models\BusinessType;
use App\Models\Device;
use App\Models\Establishment;
use App\Models\Group;
use App\Models\Location;
use App\Models\Notification;
use App\Models\Subscription;
use App\Models\User;
use App\Services\DashboardService;
use App\Services\SubscriptionService;
use Database\Seeders\DeviceSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RetiredModulesTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->actingAs(User::factory()->create(['role' => 'superadmin']));
    }

    private function establishment(): Establishment
    {
        return Establishment::create([
            'business_type_id' => BusinessType::firstOrCreate(['name' => 'Tienda'])->id,
            'name' => 'Establecimiento vigente', 'address' => 'Calle 1',
            'latitude' => 0, 'longitude' => -74,
        ]);
    }

    public function test_retired_web_and_api_routes_cannot_read_or_mutate_historical_records(): void
    {
        $location = Location::factory()->create();
        $subscription = Subscription::factory()->active()->create();

        foreach (['locations' => $location, 'subscriptions' => $subscription] as $module => $record) {
            foreach (['', '/api'] as $prefix) {
                $url = $prefix.'/'.$module;
                $this->getJson($url)->assertNotFound();
                $this->getJson($url.'/'.$record->id)->assertNotFound();
                $this->postJson($url, ['name' => 'No crear'])->assertNotFound();
                $this->putJson($url.'/'.$record->id, ['name' => 'No cambiar'])->assertNotFound();
                $this->deleteJson($url.'/'.$record->id)->assertNotFound();
            }
            $this->get('/'.$module.'/create')->assertNotFound();
            $this->get('/'.$module.'/'.$record->id.'/edit')->assertNotFound();
            $this->assertModelExists($record);
            $this->assertSame($record->name, $record->fresh()->name);
        }
        Http::assertNothingSent();
    }

    public function test_location_assignment_is_unavailable_but_device_establishment_updates_preserve_history(): void
    {
        $location = Location::factory()->create();
        $other = Location::factory()->create();
        $device = Device::factory()->create(['location_id' => $location->id]);
        $establishment = $this->establishment();

        $this->postJson('/devices/'.$device->id.'/change-location', ['location_id' => $other->id])->assertNotFound();
        $this->postJson(route('devices.bulk-operation'), [
            'device_ids' => [$device->id], 'action' => 'change_location', 'target_location_id' => $other->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('action');
        $this->putJson(route('api.devices.update', $device), [
            'name' => 'Nombre vigente', 'establishment_id' => $establishment->id, 'location_id' => $other->id,
        ])->assertOk();

        $this->assertSame($location->id, $device->fresh()->location_id);
        $this->assertSame($establishment->id, $device->fresh()->establishment_id);
        $this->assertSame('Nombre vigente', $device->fresh()->name);
        $dto = DeviceDTO::fromRequest(Request::create('/', 'POST', ['location_id' => $other->id, 'name' => 'Equipo']));
        $this->assertArrayNotHasKey('location_id', $dto->toArray());
        Http::assertNothingSent();
    }

    public function test_old_subscription_commands_jobs_and_events_leave_history_and_notifications_untouched(): void
    {
        $subscription = Subscription::factory()->active()->create(['end_date' => now()->subDays(2)]);
        $original = $subscription->fresh()->getAttributes();
        Event::fake([SubscriptionExpiring::class]);

        $this->artisan('subscriptions:check')->expectsOutputToContain('retirado')->assertSuccessful();
        unserialize(serialize(new CheckExpiringSubscriptionsJob))->handle();
        app(SendSubscriptionAlert::class)->handle(new SubscriptionExpiring($subscription, 2));

        $this->assertSame(0, app(SubscriptionService::class)->sendExpirationAlerts());
        $this->assertSame($original, $subscription->fresh()->getAttributes());
        $this->assertSame(0, Notification::count());
        Event::assertNotDispatched(SubscriptionExpiring::class);
        foreach (app(Schedule::class)->events() as $event) {
            $this->assertStringNotContainsString('subscriptions:check', $event->command ?? '');
        }
        Http::assertNothingSent();
    }

    public function test_both_navigation_layouts_keep_establishments_without_retired_links(): void
    {
        foreach (['layouts.app', 'components.app-layout'] as $layout) {
            $html = view($layout, ['slot' => 'Contenido'])->render();
            $this->assertStringNotContainsString('/locations', $html);
            $this->assertStringNotContainsString('/subscriptions', $html);
            $this->assertStringContainsString(route('establishments.index'), $html);
        }
        $this->get(route('establishments.index'))->assertOk();
        Http::assertNothingSent();
    }

    public function test_legacy_dashboard_map_prefers_establishments_and_keeps_historical_fallback(): void
    {
        $location = Location::factory()->create(['latitude' => 6, 'longitude' => -76, 'city' => 'Cali']);
        $establishment = $this->establishment();
        $current = Device::factory()->create(['establishment_id' => $establishment->id, 'location_id' => $location->id]);
        $historical = Device::factory()->create(['location_id' => $location->id, 'latitude' => null, 'longitude' => null]);
        $unmapped = Device::factory()->create(['location_id' => null, 'latitude' => null, 'longitude' => null]);

        $map = collect(app(DashboardService::class)->getMapData())->keyBy('device_id');
        $this->assertSame(0.0, (float) $map[$current->id]['latitude']);
        $this->assertSame(-74.0, (float) $map[$current->id]['longitude']);
        $this->assertSame('Calle 1', $map[$current->id]['address']);
        $this->assertSame(6.0, (float) $map[$historical->id]['latitude']);
        $this->assertFalse($map->has($unmapped->id));
        $this->assertModelExists($location);
    }

    public function test_group_details_show_establishments_instead_of_retired_location_labels(): void
    {
        $group = Group::factory()->create();
        $establishment = $this->establishment();
        Device::factory()->create(['group_id' => $group->id, 'establishment_id' => $establishment->id]);

        $this->get(route('groups.show', $group))->assertOk()
            ->assertSee('Establecimiento vigente')->assertDontSee('Ubicación');
        Http::assertNothingSent();
    }

    public function test_device_seeding_does_not_create_or_assign_locations(): void
    {
        $location = Location::factory()->create();
        $this->seed(DeviceSeeder::class);

        $this->assertSame(50, Device::count());
        $this->assertSame(0, Device::whereNotNull('location_id')->count());
        $this->assertSame(1, Location::count());
        $this->assertModelExists($location);
    }
}
