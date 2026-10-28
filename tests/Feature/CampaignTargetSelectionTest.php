<?php

namespace Tests\Feature;

use App\Jobs\ProcessCampaignDeviceJob;
use App\Models\Campaign;
use App\Models\CampaignDeviceFile;
use App\Models\Device;
use App\Models\DeviceCampaign;
use App\Models\Group;
use App\Models\Location;
use App\Models\Media;
use App\Models\User;
use App\Models\Wl35DeviceProfile;
use App\Services\Campaigns\CampaignLifecycle;
use App\Services\CampaignTargetCatalog;
use App\Services\Z2\Z2CampaignSyncService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Js;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CampaignTargetSelectionTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Bus::fake();
        config(['cache.default' => 'database', 'queue.default' => 'database', 'queue.connections.database.retry_after' => 2000]);
        Storage::fake('public');
        $this->actingAs(User::factory()->admin()->create());
    }

    private function fleet(): array
    {
        $group = Group::factory()->create(['name' => 'Grupo local']);
        $z2 = Device::factory()->create(['name' => 'Z2 Bogotá', 'mac_address' => 'AA:BB', 'city' => 'Bogotá', 'group_id' => $group->id]);
        $wl35 = Wl35DeviceProfile::create(['device_id' => 'fan-cali', 'name' => 'WL35 Cali', 'location_id' => Location::factory()->create(['city' => 'Cali'])->id]);
        $third = Device::factory()->create(['name' => 'Tercero', 'mac_address' => 'CC:DD', 'city' => 'Medellín']);

        return [$z2, $wl35, $third, $group];
    }

    private function video(): Media
    {
        $path = 'media/'.Str::uuid().'.mp4';
        Storage::disk('public')->put($path, 'local fixture');

        return Media::factory()->video()->create(['file_path' => $path, 'original_name' => 'fixture.mp4', 'duration' => 10]);
    }

    public function test_catalog_is_local_deduplicated_safe_and_uses_profile_then_location_city(): void
    {
        [$z2, $wl35] = $this->fleet();
        Device::factory()->create(['mac_address' => 'AABB']);
        $deleted = Device::factory()->create(['mac_address' => 'EEFF']);
        $deleted->delete();
        $catalog = app(CampaignTargetCatalog::class)->all();
        $this->assertCount(3, $catalog);
        $this->assertSame('Bogotá', $catalog['z2:AABB']['city']);
        $this->assertSame('Cali', $catalog['wl35:fan-cali']['city']);
        $this->assertSame(route('devices.wl35.show', $wl35->device_id), $catalog['wl35:fan-cali']['detail_url']);
        $this->assertArrayNotHasKey('contact_email', $catalog['wl35:fan-cali']);
        $this->assertArrayNotHasKey('z2:EEFF', $catalog->all());
        Http::assertNothingSent();
    }

    public function test_cross_city_mixed_selection_normalizes_keys_and_preserves_omitted_fields(): void
    {
        [, , , $group] = $this->fleet();
        $this->post(route('campaigns.store'), [
            'name' => 'Dos ciudades', 'start_date' => '2026-10-10', 'is_permanent' => 1,
            'target_devices' => ['z2:aa-bb', 'wl35:fan-cali'], 'cities' => ['Bogotá', 'Cali'], 'groups' => [$group->id],
        ])->assertSessionHasNoErrors();
        $campaign = Campaign::sole();
        $this->assertSame(['z2:AABB', 'wl35:fan-cali'], $campaign->target_devices);
        $this->assertSame(['Bogotá', 'Cali'], $campaign->segment_cities);
        $this->assertSame([$group->id], $campaign->segment_groups);
        $this->patchJson(route('api.campaigns.update', $campaign), ['name' => 'Renombrada'])->assertOk();
        $this->assertSame($campaign->target_devices, $campaign->fresh()->target_devices);
        $this->put(route('campaigns.update', $campaign), ['cities' => [], 'groups' => [], 'target_selection_present' => 1])->assertSessionHasNoErrors();
        $this->assertSame([], $campaign->fresh()->target_devices);
        $this->assertSame([], $campaign->fresh()->segment_cities);
        $this->assertSame([], $campaign->fresh()->segment_groups);
        Bus::assertNothingDispatched();
        Http::assertNothingSent();
    }

    public static function invalidTargets(): array
    {
        return [
            [['target_devices' => ['z2:AABB', 'z2:aa-bb']], 'target_devices.0'],
            [['target_devices' => ['wl35:unknown']], 'target_devices.0'],
            [['target_devices' => [['nested' => 'z2:AABB']]], 'target_devices.0'],
            [['target_devices' => null], 'target_devices'],
            [['cities' => [['nested' => 'Bogotá']]], 'segment_cities.0'],
            [['groups' => [99999]], 'segment_groups.0'],
        ];
    }

    #[DataProvider('invalidTargets')]
    public function test_invalid_targets_and_facets_never_mutate_campaign(mixed $changes, string $field): void
    {
        $this->fleet();
        $campaign = Campaign::factory()->create(['name' => 'Original', 'target_devices' => ['wl35:fan-cali']]);
        $this->patchJson(route('api.campaigns.update', $campaign), $changes + ['name' => 'No guardar'])
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertSame('Original', $campaign->fresh()->name);
        $this->assertSame(['wl35:fan-cali'], $campaign->fresh()->target_devices);
        Bus::assertNothingDispatched();
        Http::assertNothingSent();
    }

    public function test_deleted_targets_are_invalid_and_form_restores_cross_city_selection(): void
    {
        [$z2] = $this->fleet();
        $z2->delete();
        $campaign = Campaign::factory()->create();
        $keys = ['z2:AABB', 'wl35:fan-cali'];
        $this->from(route('campaigns.edit', $campaign))->put(route('campaigns.update', $campaign), ['target_devices' => $keys])
            ->assertSessionHasErrors('target_devices.0');
        $this->get(route('campaigns.edit', $campaign))->assertOk()->assertSee('ya no está disponible')
            ->assertSee(Js::from($keys)->toHtml(), false)->assertSee('data-campaign-target-selector', false);
        Http::assertNothingSent();
    }

    public function test_details_and_device_associations_respect_explicit_keys_instead_of_legacy_pivot(): void
    {
        [$z2, $wl35, $third] = $this->fleet();
        $campaign = Campaign::factory()->create(['target_devices' => ['z2:AABB', 'wl35:fan-cali']]);
        DeviceCampaign::create(['campaign_id' => $campaign->id, 'device_id' => $third->id]);
        $this->get(route('campaigns.show', $campaign))->assertOk()->assertSee('Z2 Bogotá')->assertSee('WL35 Cali')->assertDontSee('Tercero');
        $this->assertSame([$campaign->id], Campaign::forTarget('z2:AABB', $z2->id)->pluck('id')->all());
        $this->assertSame([$campaign->id], Campaign::forTarget('wl35:fan-cali')->pluck('id')->all());
        $this->assertCount(0, Campaign::forTarget('z2:CCDD', $third->id)->get());
        $campaign->update(['target_devices' => []]);
        $this->assertCount(0, Campaign::forTarget('z2:CCDD', $third->id)->get());
        $campaign->update(['target_devices' => null]);
        $this->assertSame([$campaign->id], Campaign::forTarget('z2:CCDD', $third->id)->pluck('id')->all());
        Http::assertNothingSent();
    }

    public function test_legacy_detail_handles_missing_mac_without_500(): void
    {
        $device = Device::factory()->create(['mac_address' => '']);
        $campaign = Campaign::factory()->create();
        DeviceCampaign::create(['campaign_id' => $campaign->id, 'device_id' => $device->id]);
        $this->get(route('campaigns.show', $campaign))->assertOk();
        $this->assertCount(0, app(CampaignTargetCatalog::class)->forCampaign($campaign));
    }

    public function test_publication_records_every_ordered_video_for_exact_mixed_targets(): void
    {
        [, , $third] = $this->fleet();
        $campaign = Campaign::factory()->draft()->create(['target_devices' => ['wl35:fan-cali', 'z2:AABB']]);
        $first = $this->video();
        $second = $this->video();
        $campaign->media()->attach([$second->id => ['order' => 2], $first->id => ['order' => 1]]);
        $service = app(Z2CampaignSyncService::class);
        $this->assertTrue($service->publishToDevices($campaign, [$third->id]));
        $this->assertTrue($service->publishToDevices($campaign->fresh(), [$third->id]));
        $this->assertDatabaseCount('campaign_device_files', 4);
        $this->assertDatabaseCount('campaign_deliveries', 4);
        foreach (['wl35:fan-cali', 'z2:AABB'] as $target) {
            $deliveries = $campaign->deliveries()->whereHas('file', fn ($query) => $query->where('target_key', $target))
                ->with('file')->orderBy('position')->get();
            $this->assertSame([$first->id, $second->id], $deliveries->map(fn ($delivery) => $delivery->file->media_id)->all());
            $this->assertSame([1, 2], $deliveries->pluck('position')->all());
            $this->assertSame(['pending', 'pending'], $deliveries->map(fn ($delivery) => $delivery->file->state)->all());
        }
        $this->assertSame('active', $campaign->fresh()->status);
        $this->assertSame(0, CampaignDeviceFile::where('target_key', 'z2:CCDD')->count());
        $this->assertDatabaseCount('fleet_uploads', 0);
        Bus::assertNothingDispatched();
        app(CampaignLifecycle::class)->tick();
        Bus::assertDispatchedTimes(ProcessCampaignDeviceJob::class, 2);
        Bus::assertDispatched(ProcessCampaignDeviceJob::class, fn ($job) => $job->target === 'wl35:fan-cali');
        Bus::assertDispatched(ProcessCampaignDeviceJob::class, fn ($job) => $job->target === 'z2:AABB');
        Http::assertNothingSent();
    }

    public function test_empty_explicit_selection_is_rejected_and_legacy_is_snapshotted_without_fallback(): void
    {
        [$z2] = $this->fleet();
        $campaign = Campaign::factory()->draft()->create(['target_devices' => []]);
        $media = $this->video();
        $campaign->media()->attach($media);
        $service = app(Z2CampaignSyncService::class);
        try {
            $service->publishToDevices($campaign, [$z2->id]);
            $this->fail('An explicit empty selection must not publish to legacy device IDs.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('target_devices', $exception->errors());
        }
        $this->assertSame('draft', $campaign->fresh()->status);
        $this->assertDatabaseCount('campaign_device_files', 0);
        $campaign->update(['target_devices' => null]);
        $this->assertTrue($service->publishToDevices($campaign, [$z2->id]));
        $this->assertSame(['z2:AABB'], $campaign->fresh()->target_devices);
        $file = CampaignDeviceFile::sole();
        $this->assertSame('z2:AABB', $file->target_key);
        $this->assertSame($media->id, $file->media_id);
        $this->assertSame('pending', $file->state);
        Bus::assertNothingDispatched();
        Http::assertNothingSent();
    }

    public function test_stale_target_is_rejected_before_active_callback_changes_media(): void
    {
        [$z2] = $this->fleet();
        $campaign = Campaign::factory()->create(['status' => 'active', 'target_devices' => ['z2:AABB']]);
        $media = $this->video();
        $z2->delete();
        $this->post(route('campaigns.add-media', $campaign), ['campaign_id' => $campaign->id, 'media_ids' => [$media->id]])->assertSessionHas('error');
        $this->assertDatabaseCount('campaign_media', 0);
        Bus::assertNothingDispatched();
    }

    public function test_activation_and_active_addition_record_all_media_without_synchronous_hardware_commands(): void
    {
        $this->fleet();
        $campaign = Campaign::factory()->draft()->create(['target_devices' => ['wl35:fan-cali']]);
        $first = $this->video();
        $second = $this->video();
        $campaign->media()->attach($first, ['order' => 1]);
        $this->put(route('campaigns.update', $campaign), ['name' => 'Editada'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('campaign_deliveries', 0);
        $this->post(route('campaigns.activate', $campaign))->assertSessionHas('success');
        $this->assertSame('active', $campaign->fresh()->status);
        $this->assertDatabaseCount('campaign_deliveries', 1);
        $this->post(route('campaigns.add-media', $campaign), ['campaign_id' => $campaign->id, 'media_ids' => [$second->id]])->assertSessionHas('success');
        $this->assertDatabaseCount('campaign_deliveries', 2);
        $expectedOrder = $campaign->media()->pluck('media.id')->all();
        $this->assertEqualsCanonicalizing([$first->id, $second->id], $expectedOrder);
        $this->assertSame($expectedOrder, $campaign->deliveries()->with('file')->orderBy('position')->get()->map(fn ($delivery) => $delivery->file->media_id)->all());
        $this->assertSame(['wl35:fan-cali'], CampaignDeviceFile::distinct()->pluck('target_key')->all());
        $this->assertDatabaseCount('fleet_uploads', 0);
        Bus::assertNothingDispatched();
        Http::assertNothingSent();
    }

    public function test_technician_cannot_activate_or_create_campaign_deliveries(): void
    {
        $this->fleet();
        $campaign = Campaign::factory()->draft()->create(['target_devices' => ['wl35:fan-cali']]);
        $campaign->media()->attach($this->video(), ['order' => 1]);
        $this->actingAs(User::factory()->create(['role' => 'tecnico']));
        $this->post(route('campaigns.activate', $campaign))->assertForbidden();
        $this->assertSame('draft', $campaign->fresh()->status);
        $this->assertDatabaseCount('campaign_device_files', 0);
        $this->assertDatabaseCount('campaign_deliveries', 0);
        Bus::assertNothingDispatched();
        Http::assertNothingSent();
    }

    public static function rejectedPublication(): array
    {
        return [['sync', 'video'], ['deferred', 'video'], ['background', 'video'], ['failover', 'video'], ['database', 'missing'], ['database', 'image'], ['redis', 'video']];
    }

    public function test_removing_last_media_releases_its_durable_delivery_for_cleanup(): void
    {
        $this->fleet();
        $campaign = Campaign::factory()->active()->create(['target_devices' => ['wl35:fan-cali']]);
        $media = $this->video();
        $campaign->media()->attach($media);
        app(Z2CampaignSyncService::class)->activate($campaign);
        $this->assertDatabaseCount('campaign_deliveries', 1);
        $this->post(route('campaigns.remove-media', [$campaign, $media]))->assertSessionHas('success');
        $this->assertCount(0, $campaign->fresh()->media);
        $this->assertSame('active', $campaign->fresh()->status);
        $this->assertDatabaseCount('campaign_deliveries', 1);
        $this->assertNotNull($campaign->deliveries()->sole()->released_at);
        $this->assertDatabaseCount('fleet_uploads', 0);
        Bus::assertNothingDispatched();
        Http::assertNothingSent();
    }

    #[DataProvider('rejectedPublication')]
    public function test_unusable_queue_or_media_cannot_dispatch(string $queue, string $kind): void
    {
        $this->fleet();
        config(['queue.default' => $queue]);
        $campaign = Campaign::factory()->draft()->create(['target_devices' => ['z2:AABB']]);
        $media = $kind === 'video' ? $this->video() : Media::factory()->create(['file_path' => 'media/missing.mp4', 'mime_type' => $kind === 'image' ? 'image/png' : 'video/mp4']);
        $campaign->media()->attach($media);
        try {
            app(Z2CampaignSyncService::class)->publishToDevices($campaign, []);
            $this->fail('La publicación debería fallar antes de encolarse.');
        } catch (\RuntimeException|ValidationException $exception) {
            $this->assertSame('draft', $campaign->fresh()->status);
            $this->assertDatabaseCount('campaign_deliveries', 0);
            $this->assertDatabaseCount('campaign_device_files', 0);
            if ($exception instanceof ValidationException) {
                $this->assertArrayHasKey('media_ids', $exception->errors());
            }
            $this->assertDatabaseCount('fleet_uploads', 0);
            Bus::assertNothingDispatched();
        }
        Http::assertNothingSent();
    }

    public function test_migration_preserves_legacy_and_refuses_loss_of_explicit_recipient_semantics(): void
    {
        $migration = require database_path('migrations/2026_10_11_000001_add_target_devices_to_campaigns_table.php');
        $campaign = Campaign::factory()->create(['target_devices' => []]);
        try {
            $migration->down();
            $this->fail('No debe retirar una selección explícita silenciosamente.');
        } catch (\RuntimeException) {
            $this->assertTrue(Schema::hasColumn('campaigns', 'target_devices'));
        }
        $campaign->update(['target_devices' => null]);
        $migration->down();
        $this->assertFalse(Schema::hasColumn('campaigns', 'target_devices'));
        $migration->up();
        $this->assertNull($campaign->fresh()->target_devices);
    }
}
