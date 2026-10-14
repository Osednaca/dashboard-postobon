<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PermanentCampaignTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->actingAs(User::factory()->admin()->create());
    }

    private function payload(array $overrides = []): array
    {
        return array_merge(['name' => 'Campaña continua', 'start_date' => '2026-10-10', 'is_permanent' => 1], $overrides);
    }

    public function test_creation_normalizes_explicit_permanence_and_keeps_start_and_draft(): void
    {
        $this->post(route('campaigns.store'), $this->payload(['end_date' => 'not-a-date']))
            ->assertRedirect(route('campaigns.index'))->assertSessionHasNoErrors();
        $campaign = Campaign::sole();
        $this->assertNull($campaign->end_date);
        $this->assertSame('2026-10-10', $campaign->start_date->toDateString());
        $this->assertSame('draft', $campaign->status);
        $this->assertArrayNotHasKey('is_permanent', $campaign->getAttributes());
        Http::assertNothingSent();
    }

    public function test_edit_can_switch_to_permanent_and_back_to_finite_using_stored_start(): void
    {
        $campaign = Campaign::factory()->create(['start_date' => '2026-10-10', 'end_date' => '2026-11-01', 'status' => 'paused']);
        $this->put(route('campaigns.update', $campaign), ['is_permanent' => 1])
            ->assertRedirect(route('campaigns.index'))->assertSessionHasNoErrors();
        $this->assertNull($campaign->fresh()->end_date);
        $this->put(route('campaigns.update', $campaign), ['is_permanent' => 0, 'end_date' => '2026-12-01'])
            ->assertRedirect(route('campaigns.index'))->assertSessionHasNoErrors();
        $this->assertSame('2026-12-01', $campaign->fresh()->end_date->toDateString());
        $this->assertSame('paused', $campaign->fresh()->status);
        Http::assertNothingSent();
    }

    public static function invalidDates(): array
    {
        return [[['is_permanent' => 0, 'end_date' => null], 'end_date'],
            [['is_permanent' => 0, 'end_date' => '2026-10-09'], 'end_date'],
            [['is_permanent' => 0, 'end_date' => 'invalid'], 'end_date'],
            [['is_permanent' => 'invalid', 'end_date' => '2026-11-01'], 'is_permanent'],
            [['start_date' => null], 'start_date']];
    }

    #[DataProvider('invalidDates')]
    public function test_creation_rejects_invalid_temporal_ranges_flags_and_missing_start(array $changes, string $field): void
    {
        $this->post(route('campaigns.store'), $this->payload($changes))->assertSessionHasErrors($field);
        $this->assertSame(0, Campaign::count());
        Http::assertNothingSent();
    }

    public function test_partial_edit_preserves_dates_and_rejects_invalid_finite_switch(): void
    {
        $campaign = Campaign::factory()->create(['start_date' => '2026-10-10', 'end_date' => '2026-11-01']);
        $this->put(route('campaigns.update', $campaign), ['name' => 'Renombrada'])->assertSessionHasNoErrors();
        $this->assertSame('2026-11-01', $campaign->fresh()->end_date->toDateString());
        $this->put(route('campaigns.update', $campaign), ['is_permanent' => 0])->assertSessionHasErrors('end_date');
        $this->put(route('campaigns.update', $campaign), ['is_permanent' => 0, 'end_date' => '2026-10-09'])
            ->assertSessionHasErrors('end_date');
        $this->assertSame('2026-11-01', $campaign->fresh()->end_date->toDateString());
        Http::assertNothingSent();
    }

    public function test_existing_null_dates_and_finite_records_render_and_restore_form_state(): void
    {
        $permanent = Campaign::factory()->create(['start_date' => null, 'end_date' => null]);
        $finite = Campaign::factory()->create(['start_date' => '2026-10-10', 'end_date' => '2026-11-01']);
        $this->get(route('campaigns.create'))->assertOk()->assertSee('Campaña permanente')
            ->assertSee(':disabled="permanent"', false)->assertSee('name="start_date"', false);
        $this->get(route('campaigns.edit', $permanent))->assertOk()->assertSee('Campaña permanente')
            ->assertSee('x-model="permanent" checked', false)->assertSee('disabled', false);
        $this->get(route('campaigns.edit', $finite))->assertOk()->assertSee('value="2026-11-01"', false)
            ->assertDontSee('x-model="permanent" checked', false);
        $this->get(route('campaigns.index'))->assertOk()->assertSee('Permanente · Sin fecha de fin')->assertSee('2026-11-01');
        $this->get(route('campaigns.show', $permanent))->assertOk()->assertSee('Permanente · Sin fecha de fin');
        $this->withSession(['_old_input' => ['is_permanent' => '0', 'end_date' => '2026-12-02']])
            ->get(route('campaigns.edit', $permanent))->assertOk()->assertSee('value="2026-12-02"', false)
            ->assertDontSee('x-model="permanent" checked', false);
        Http::assertNothingSent();
    }

    public function test_partial_start_update_validates_saved_end_without_resetting_omitted_fields(): void
    {
        $campaign = Campaign::factory()->create(['start_date' => '2026-10-10', 'end_date' => '2026-11-01']);
        $this->patchJson(route('campaigns.update', $campaign), ['start_date' => '2026-12-01'])
            ->assertUnprocessable()->assertJsonValidationErrors('end_date');
        $this->assertSame('2026-10-10', $campaign->fresh()->start_date->toDateString());
        $this->patch(route('campaigns.update', $campaign), ['start_date' => '2026-10-20'])->assertSessionHasNoErrors();
        $this->assertSame('2026-10-20', $campaign->fresh()->start_date->toDateString());
        $this->assertSame('2026-11-01', $campaign->fresh()->end_date->toDateString());
        Http::assertNothingSent();
    }

    public function test_malformed_start_is_validation_error_and_keeps_saved_range(): void
    {
        $campaign = Campaign::factory()->create(['start_date' => '2026-10-10', 'end_date' => '2026-11-01']);
        foreach ([['nested' => '2026-10-20'], 'invalid-date'] as $start) {
            $this->patchJson(route('campaigns.update', $campaign), ['start_date' => $start, 'end_date' => '2026-12-01'])
                ->assertUnprocessable()->assertJsonValidationErrors('start_date');
        }
        $this->assertSame('2026-10-10', $campaign->fresh()->start_date->toDateString());
        $this->assertSame('2026-11-01', $campaign->fresh()->end_date->toDateString());
        Http::assertNothingSent();
    }
}
