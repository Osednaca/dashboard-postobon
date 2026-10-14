<?php

namespace Tests\Feature;

use App\Models\BusinessType;
use App\Models\Campaign;
use App\Models\Device;
use App\Models\Establishment;
use App\Models\FleetUpload;
use App\Models\Group;
use App\Models\Media;
use App\Models\User;
use App\Models\Wl35DeviceProfile;
use App\Services\CampaignTargetCatalog;
use App\Services\Z2\Z2CampaignSyncService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Js;
use Tests\TestCase;

class CampaignAudienceTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Bus::fake();
        Storage::fake('public');
        $this->actingAs(User::factory()->create(['role' => 'campanas']));
    }

    private function audience(): array
    {
        $oxxo = BusinessType::create(['name' => 'Oxxo']);
        $exito = BusinessType::create(['name' => 'Éxito']);
        $medellin = Group::factory()->create(['name' => 'Medellín', 'business_type_id' => $exito->id]);
        $bogota = Group::factory()->create(['name' => 'Bogotá']);
        $oxxoStore = Establishment::create(['name' => 'Oxxo Centro', 'address' => 'Calle 1', 'business_type_id' => $oxxo->id]);
        $exitoStore = Establishment::create(['name' => 'Éxito de la 80', 'address' => 'Carrera 80', 'business_type_id' => $exito->id]);
        $otherExito = Establishment::create(['name' => 'Éxito vecino', 'address' => 'Calle 2', 'business_type_id' => $exito->id]);
        Device::factory()->create(['name' => 'Oxxo 42', 'mac_address' => 'AA:BB', 'group_id' => $medellin->id, 'establishment_id' => $oxxoStore->id]);
        Device::factory()->create(['name' => 'Duplicado físico', 'mac_address' => 'AABB', 'group_id' => $medellin->id, 'establishment_id' => $oxxoStore->id]);
        Wl35DeviceProfile::create(['name' => 'Oxxo 35', 'device_id' => 'oxxo35', 'group_id' => $medellin->id, 'establishment_id' => $oxxoStore->id]);
        Device::factory()->create(['name' => 'Oxxo otra ciudad', 'mac_address' => 'BB:CC', 'group_id' => $bogota->id, 'establishment_id' => $oxxoStore->id]);
        Device::factory()->create(['name' => 'Éxito 80', 'mac_address' => 'CC:DD', 'group_id' => $medellin->id, 'establishment_id' => $exitoStore->id]);
        Wl35DeviceProfile::create(['name' => 'Éxito 80 35', 'device_id' => 'exito35', 'establishment_id' => $exitoStore->id]);
        Device::factory()->create(['name' => 'Otro Éxito', 'mac_address' => 'DD:EE', 'group_id' => $medellin->id, 'establishment_id' => $otherExito->id]);
        Device::factory()->create(['name' => 'Sin establecimiento', 'mac_address' => 'EE:FF', 'group_id' => $medellin->id, 'establishment' => 'Oxxo']);

        return compact('oxxo', 'exito', 'medellin', 'bogota', 'oxxoStore', 'exitoStore');
    }

    private function createPayload(array $criteria): array
    {
        return $criteria + ['name' => 'Audiencia nueva', 'start_date' => '2026-10-14', 'is_permanent' => 1];
    }

    public function test_all_mode_saves_every_known_identity_once_and_discards_irrelevant_client_criteria(): void
    {
        $this->audience();
        Device::factory()->create(['mac_address' => 'FF:AA', 'group_id' => null, 'establishment_id' => null]);
        Wl35DeviceProfile::create(['name' => 'Sin asignar 35', 'device_id' => 'unassigned35']);
        $this->postJson(route('api.campaigns.store'), $this->createPayload([
            'audience_mode' => 'all', 'target_devices' => ['wl35:unknown'],
            'segment_groups' => [999999], 'segment_cities' => 'ignored',
            'audience_business_type_ids' => [999999], 'audience_establishment_id' => 999999,
        ]))->assertCreated();

        $campaign = Campaign::sole();
        $this->assertSame('all', $campaign->audience_mode);
        $this->assertSame(['z2:AABB', 'z2:BBCC', 'z2:CCDD', 'z2:DDEE', 'z2:EEFF', 'z2:FFAA',
            'wl35:oxxo35', 'wl35:exito35', 'wl35:unassigned35'], $campaign->target_devices);
        $this->assertSame([], $campaign->segment_groups);
        $this->assertSame([], $campaign->segment_cities);
        $this->assertSame([], $campaign->audience_business_type_ids);
        $this->assertNull($campaign->audience_establishment_id);
        $this->get(route('campaigns.show', $campaign))->assertOk()->assertSee('Todos los dispositivos')
            ->assertSee('Destinatarios fijados al guardar');
        Http::assertNothingSent();
    }

    public function test_all_mode_rejects_empty_inventory_without_creating_a_campaign(): void
    {
        $this->postJson(route('api.campaigns.store'), $this->createPayload(['audience_mode' => 'all']))
            ->assertUnprocessable()->assertJsonValidationErrors('audience_mode');
        $this->assertSame(0, Campaign::count());
        Http::assertNothingSent();
    }

    public function test_all_mode_keeps_its_snapshot_on_partial_edit_and_publication_until_explicitly_resaved(): void
    {
        Device::factory()->create(['mac_address' => 'AA:BB', 'group_id' => null, 'establishment_id' => null]);
        Wl35DeviceProfile::create(['name' => 'Equipo 35', 'device_id' => 'all35']);
        $this->postJson(route('api.campaigns.store'), $this->createPayload(['audience_mode' => 'all']))->assertCreated();
        $campaign = Campaign::sole();
        Device::factory()->create(['mac_address' => 'CC:DD']);
        $this->patchJson(route('api.campaigns.update', $campaign), ['name' => 'Renombrada'])->assertOk();
        $this->assertSame(['z2:AABB', 'wl35:all35'], $campaign->fresh()->target_devices);
        foreach ([['target_devices' => ['z2:CCDD']], ['segment_groups' => []], ['segment_cities' => []]] as $criteria) {
            $this->patchJson(route('api.campaigns.update', $campaign), $criteria)
                ->assertUnprocessable()->assertJsonValidationErrors('audience_mode');
        }

        Storage::disk('public')->put('media/all-audience.mp4', 'fixture');
        $media = Media::factory()->video()->create(['file_path' => 'media/all-audience.mp4']);
        $campaign->media()->attach($media);
        config(['queue.default' => 'database']);
        app(Z2CampaignSyncService::class)->publishToDevices($campaign->fresh(), []);
        $this->assertSame(['z2:AABB', 'wl35:all35'], FleetUpload::sole()->targets);

        $this->patchJson(route('api.campaigns.update', $campaign), ['audience_mode' => 'all'])->assertOk();
        $this->assertSame(['z2:AABB', 'z2:CCDD', 'wl35:all35'], $campaign->fresh()->target_devices);
        $this->patchJson(route('api.campaigns.update', $campaign), ['audience_mode' => 'manual', 'target_devices' => ['z2:CCDD']])->assertOk();
        $this->assertSame('manual', $campaign->fresh()->audience_mode);
        $this->assertSame(['z2:CCDD'], $campaign->fresh()->target_devices);
        Http::assertNothingSent();
    }

    public function test_all_mode_restores_on_edit_and_validation_retry(): void
    {
        Device::factory()->create(['mac_address' => 'AA:BB']);
        $this->post(route('campaigns.store'), $this->createPayload(['audience_mode' => 'all']))
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $campaign = Campaign::sole();
        $expected = ['mode' => 'all', 'businessTypes' => [], 'groups' => [], 'establishment' => null];
        $this->get(route('campaigns.edit', $campaign))->assertOk()->assertSee(Js::from($expected)->toHtml(), false);
        $this->from(route('campaigns.edit', $campaign))->put(route('campaigns.update', $campaign),
            ['audience_mode' => 'all', 'name' => str_repeat('x', 256)])->assertSessionHasErrors('name');
        $this->get(route('campaigns.edit', $campaign))->assertOk()->assertSee(Js::from($expected)->toHtml(), false);
        $this->assertSame(['z2:AABB'], $campaign->fresh()->target_devices);
    }

    public function test_type_and_group_intersection_saves_only_matching_physical_devices(): void
    {
        $fixtures = $this->audience();
        $criteria = ['audience_mode' => 'segment', 'audience_business_type_ids' => [$fixtures['oxxo']->id], 'segment_groups' => [$fixtures['medellin']->id]];
        $this->postJson(route('api.campaigns.store'), $this->createPayload($criteria) + ['target_devices' => ['z2:CCDD']])
            ->assertCreated();
        $campaign = Campaign::sole();
        $this->assertSame(['z2:AABB', 'wl35:oxxo35'], $campaign->target_devices);
        $this->assertSame('segment', $campaign->audience_mode);
        $this->assertSame([$fixtures['oxxo']->id], $campaign->audience_business_type_ids);
        $this->assertNull($campaign->audience_establishment_id);
        $this->assertSame([], $campaign->segment_cities);
        $this->assertSame($fixtures['oxxo']->id, app(CampaignTargetCatalog::class)->all()['z2:AABB']['business_type_id']);
        $this->assertSame($fixtures['exito']->id, $fixtures['medellin']->business_type_id);
        Bus::assertNothingDispatched();
        Http::assertNothingSent();
    }

    public function test_multiple_types_and_groups_use_or_within_each_dimension(): void
    {
        $f = $this->audience();
        $this->postJson(route('api.campaigns.store'), $this->createPayload([
            'audience_mode' => 'segment', 'audience_business_type_ids' => [$f['oxxo']->id, $f['exito']->id],
            'segment_groups' => [$f['medellin']->id, $f['bogota']->id],
        ]))->assertCreated();
        $this->assertSame(['z2:AABB', 'z2:BBCC', 'z2:CCDD', 'z2:DDEE', 'wl35:oxxo35'], Campaign::sole()->target_devices);
    }

    public function test_direct_establishment_excludes_same_business_type_and_ignores_groups(): void
    {
        $f = $this->audience();
        $this->post(route('campaigns.store'), $this->createPayload([
            'audience_mode' => 'establishment', 'audience_establishment_id' => $f['exitoStore']->id,
            'audience_business_type_ids' => [$f['oxxo']->id], 'segment_groups' => [$f['bogota']->id],
        ]))->assertSessionHasNoErrors()->assertSessionHas('success');
        $campaign = Campaign::sole();
        $this->assertSame(['z2:CCDD', 'wl35:exito35'], $campaign->target_devices);
        $this->assertSame([], $campaign->segment_groups);
        $this->assertSame([], $campaign->audience_business_type_ids);
        $this->assertSame($f['exitoStore']->id, $campaign->audience_establishment_id);
        $this->get(route('campaigns.show', $campaign))->assertOk()->assertSee('Éxito de la 80')->assertSee('Destinatarios fijados al guardar');
    }

    public function test_invalid_or_empty_criteria_reject_save_and_leave_previous_audience_untouched(): void
    {
        $f = $this->audience();
        $empty = Establishment::create(['name' => 'Vacío', 'address' => 'Calle 3', 'business_type_id' => $f['exito']->id]);
        $campaign = Campaign::factory()->create(['name' => 'Original', 'target_devices' => ['z2:AABB']]);
        foreach ([
            [['audience_mode' => 'wrong'], 'audience_mode'],
            [['audience_mode' => 'segment'], 'audience_mode'],
            [['audience_mode' => 'segment', 'audience_business_type_ids' => [999999]], 'audience_business_type_ids.0'],
            [['audience_mode' => 'segment', 'segment_groups' => [999999]], 'segment_groups.0'],
            [['audience_mode' => 'segment', 'audience_business_type_ids' => [$f['exito']->id], 'segment_groups' => [$f['bogota']->id]], 'audience_mode'],
            [['audience_mode' => 'establishment'], 'audience_establishment_id'],
            [['audience_mode' => 'establishment', 'audience_establishment_id' => 999999], 'audience_establishment_id'],
            [['audience_mode' => 'establishment', 'audience_establishment_id' => $empty->id], 'audience_mode'],
            [['audience_business_type_ids' => [$f['oxxo']->id]], 'audience_mode'],
        ] as [$criteria, $field]) {
            $this->patchJson(route('api.campaigns.update', $campaign), $criteria + ['name' => 'No guardar'])
                ->assertUnprocessable()->assertJsonValidationErrors($field);
            $this->assertSame('Original', $campaign->fresh()->name);
            $this->assertSame(['z2:AABB'], $campaign->fresh()->target_devices);
        }
        Http::assertNothingSent();
    }

    public function test_partial_edit_preserves_snapshot_until_criteria_are_explicitly_resaved_and_manual_can_replace_it(): void
    {
        $f = $this->audience();
        $criteria = ['audience_mode' => 'segment', 'audience_business_type_ids' => [$f['oxxo']->id], 'segment_groups' => [$f['medellin']->id]];
        $this->postJson(route('api.campaigns.store'), $this->createPayload($criteria))->assertCreated();
        $campaign = Campaign::sole();
        Device::factory()->create(['mac_address' => 'FF:AA', 'group_id' => $f['medellin']->id, 'establishment_id' => $f['oxxoStore']->id]);
        $this->patchJson(route('api.campaigns.update', $campaign), ['name' => 'Renombrada'])->assertOk();
        $this->assertSame(['z2:AABB', 'wl35:oxxo35'], $campaign->fresh()->target_devices);
        $this->patchJson(route('api.campaigns.update', $campaign), ['segment_groups' => [$f['bogota']->id]])
            ->assertUnprocessable()->assertJsonValidationErrors('audience_mode');
        $this->patchJson(route('api.campaigns.update', $campaign), ['target_devices' => ['z2:CCDD']])
            ->assertUnprocessable()->assertJsonValidationErrors('audience_mode');
        $this->patchJson(route('api.campaigns.update', $campaign), $criteria)->assertOk();
        $this->assertSame(['z2:AABB', 'z2:FFAA', 'wl35:oxxo35'], $campaign->fresh()->target_devices);
        $this->patchJson(route('api.campaigns.update', $campaign), ['audience_mode' => 'manual', 'target_devices' => ['z2:CCDD']])->assertOk();
        $this->assertSame(['z2:CCDD'], $campaign->fresh()->target_devices);
        $this->assertSame([], $campaign->fresh()->audience_business_type_ids);
        $this->assertNull($campaign->fresh()->audience_establishment_id);
    }

    public function test_edit_and_failed_validation_restore_criteria_without_exposing_establishment_secrets(): void
    {
        $f = $this->audience();
        $f['oxxoStore']->update(['wifi_password' => 'private-wifi-123', 'contact_phone' => 'private-phone-456']);
        $criteria = ['audience_mode' => 'segment', 'audience_business_type_ids' => [$f['oxxo']->id], 'segment_groups' => [$f['medellin']->id]];
        $this->postJson(route('api.campaigns.store'), $this->createPayload($criteria))->assertCreated();
        $campaign = Campaign::sole();
        $expected = ['mode' => 'segment', 'businessTypes' => [$f['oxxo']->id], 'groups' => [$f['medellin']->id], 'establishment' => null];
        $this->get(route('campaigns.edit', $campaign))->assertOk()->assertSee(Js::from($expected)->toHtml(), false)
            ->assertDontSee('private-wifi-123')->assertDontSee('private-phone-456');
        $this->from(route('campaigns.edit', $campaign))->put(route('campaigns.update', $campaign),
            ['audience_mode' => 'establishment', 'audience_establishment_id' => $f['exitoStore']->id, 'name' => str_repeat('x', 256)])
            ->assertSessionHasErrors('name');
        $expected = ['mode' => 'establishment', 'businessTypes' => [], 'groups' => [], 'establishment' => $f['exitoStore']->id];
        $this->get(route('campaigns.edit', $campaign))->assertOk()->assertSee(Js::from($expected)->toHtml(), false);
        Http::assertNothingSent();
    }

    public function test_validation_retry_keeps_deselected_criteria_empty_instead_of_restoring_saved_values(): void
    {
        $f = $this->audience();
        $campaign = Campaign::factory()->create([
            'audience_mode' => 'segment', 'audience_business_type_ids' => [$f['oxxo']->id],
            'segment_groups' => [$f['medellin']->id], 'target_devices' => ['z2:AABB'],
        ]);
        $this->from(route('campaigns.edit', $campaign))->put(route('campaigns.update', $campaign),
            ['audience_mode' => 'segment', 'target_selection_present' => 1])->assertSessionHasErrors('audience_mode');
        $expected = ['mode' => 'segment', 'businessTypes' => [], 'groups' => [], 'establishment' => null];
        $this->get(route('campaigns.edit', $campaign))->assertOk()->assertSee(Js::from($expected)->toHtml(), false);
        $this->assertSame(['z2:AABB'], $campaign->fresh()->target_devices);
    }

    public function test_publication_keeps_saved_audience_even_when_group_membership_changes(): void
    {
        $f = $this->audience();
        $this->postJson(route('api.campaigns.store'), $this->createPayload([
            'audience_mode' => 'segment', 'audience_business_type_ids' => [$f['oxxo']->id], 'segment_groups' => [$f['medellin']->id],
        ]))->assertCreated();
        $campaign = Campaign::sole();
        Device::where('mac_address', 'AA:BB')->update(['group_id' => $f['bogota']->id]);
        Device::factory()->create(['mac_address' => 'FF:AA', 'group_id' => $f['medellin']->id, 'establishment_id' => $f['oxxoStore']->id]);
        Storage::disk('public')->put('media/audience.mp4', 'fixture');
        $media = Media::factory()->video()->create(['file_path' => 'media/audience.mp4']);
        $campaign->media()->attach($media);
        config(['queue.default' => 'database']);
        app(Z2CampaignSyncService::class)->publishToDevices($campaign, []);
        $this->assertSame(['z2:AABB', 'wl35:oxxo35'], FleetUpload::sole()->targets);
        Http::assertNothingSent();
    }

    public function test_criteria_migration_preserves_exact_targets_and_legacy_null(): void
    {
        $campaign = Campaign::factory()->create(['audience_mode' => 'segment', 'target_devices' => ['z2:AABB']]);
        $legacy = Campaign::factory()->create(['target_devices' => null]);
        $migration = require database_path('migrations/2026_10_14_000004_add_campaign_audience_criteria.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('campaigns', 'audience_mode'));
        $this->assertSame(['z2:AABB'], $campaign->fresh()->target_devices);
        $this->assertNull($legacy->fresh()->target_devices);
        $migration->up();
        $this->assertNull($campaign->fresh()->audience_mode);
        $this->assertSame(['z2:AABB'], $campaign->fresh()->target_devices);
    }
}
