<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CampaignDateTimeTest extends TestCase
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
        return array_merge([
            'name' => 'Campaña con horario',
            'start_date' => '2026-10-14T09:30',
            'end_date' => '2026-10-14T17:45:30',
            'is_permanent' => 0,
        ], $overrides);
    }

    public function test_combined_fields_persist_same_day_times_and_edit_keeps_seconds(): void
    {
        $this->post(route('campaigns.store'), $this->payload())
            ->assertRedirect(route('campaigns.index'))->assertSessionHasNoErrors();
        $campaign = Campaign::sole();
        $this->assertSame('2026-10-14 09:30:00', $campaign->start_date->toDateTimeString());
        $this->assertSame('2026-10-14 17:45:30', $campaign->end_date->toDateTimeString());
        $this->assertSame(config('app.timezone'), $campaign->start_date->timezoneName);

        foreach (['campaigns.create', 'campaigns.edit'] as $name) {
            $html = $this->get(route($name, $name === 'campaigns.edit' ? $campaign : []))->assertOk()->getContent();
            $document = new \DOMDocument;
            @$document->loadHTML($html);
            $xpath = new \DOMXPath($document);
            $this->assertSame(2, $xpath->query('//input[@type="datetime-local"]')->length);
            foreach (['start_date', 'end_date'] as $field) {
                $this->assertSame(1, $xpath->query('//input[@name="'.$field.'"]')->length);
            }
            $this->assertSame(0, $xpath->query('//input[@type="date" or @type="time"]')->length);
            if ($name === 'campaigns.edit') {
                $this->assertSame('2026-10-14T09:30', $xpath->query('//input[@name="start_date"]')->item(0)->getAttribute('value'));
                $this->assertSame('2026-10-14T17:45:30', $xpath->query('//input[@name="end_date"]')->item(0)->getAttribute('value'));
                $this->assertSame('1', $xpath->query('//input[@name="end_date"]')->item(0)->getAttribute('step'));
            }
        }

        $this->put(route('campaigns.update', $campaign), $this->payload(['start_date' => '2026-10-14T10:15']))
            ->assertSessionHasNoErrors();
        $this->assertSame('2026-10-14 10:15:00', $campaign->fresh()->start_date->toDateTimeString());
        $this->assertSame('2026-10-14 17:45:30', $campaign->fresh()->end_date->toDateTimeString());
        foreach (['campaigns.index', 'campaigns.show'] as $name) {
            $this->get(route($name, $name === 'campaigns.show' ? $campaign : []))->assertOk()
                ->assertSee('2026-10-14 10:15')->assertSee('2026-10-14 17:45');
        }
        Http::assertNothingSent();
    }

    public function test_same_day_end_before_start_is_rejected_without_saving(): void
    {
        $this->postJson(route('api.campaigns.store'), $this->payload(['end_date' => '2026-10-14T09:29:59']))
            ->assertUnprocessable()->assertJsonValidationErrors('end_date');
        $this->assertSame(0, Campaign::count());
        Http::assertNothingSent();
    }

    public function test_partial_updates_compare_times_with_omitted_saved_boundaries(): void
    {
        $campaign = Campaign::factory()->create(['start_date' => '2026-10-14 09:30:00', 'end_date' => '2026-10-14 17:45:30']);
        foreach ([['start_date' => '2026-10-14T17:45:31'], ['end_date' => '2026-10-14T09:29:59']] as $payload) {
            $this->patchJson(route('api.campaigns.update', $campaign), $payload)
                ->assertUnprocessable()->assertJsonValidationErrors('end_date');
        }
        $this->patchJson(route('api.campaigns.update', $campaign), ['start_date' => '2026-10-14T12:15'])
            ->assertOk();
        $this->assertSame('2026-10-14 12:15:00', $campaign->fresh()->start_date->toDateTimeString());
        $this->assertSame('2026-10-14 17:45:30', $campaign->fresh()->end_date->toDateTimeString());
        $this->patchJson(route('api.campaigns.update', $campaign), ['end_date' => '2026-10-14T12:15'])
            ->assertOk();
        $this->assertSame('2026-10-14 12:15:00', $campaign->fresh()->end_date->toDateTimeString());
        Http::assertNothingSent();
    }

    public function test_date_only_api_input_remains_compatible_and_renders_midnight(): void
    {
        $this->postJson(route('api.campaigns.store'), $this->payload([
            'start_date' => '2026-10-14', 'end_date' => '2026-10-15',
        ]))->assertCreated();
        $campaign = Campaign::sole();
        $this->assertSame('2026-10-14 00:00:00', $campaign->start_date->toDateTimeString());
        $this->get(route('campaigns.edit', $campaign))->assertOk()
            ->assertSee('value="2026-10-14T00:00"', false)->assertSee('value="2026-10-15T00:00"', false);
        $this->patchJson(route('api.campaigns.update', $campaign), ['end_date' => '2026-10-16'])->assertOk();
        $this->assertSame('2026-10-16 00:00:00', $campaign->fresh()->end_date->toDateTimeString());
        Http::assertNothingSent();
    }

    public function test_permanent_campaign_keeps_start_time_and_null_end_when_created_and_edited(): void
    {
        $this->post(route('campaigns.store'), $this->payload(['is_permanent' => 1]))->assertSessionHasNoErrors();
        $campaign = Campaign::sole();
        $this->assertSame('2026-10-14 09:30:00', $campaign->start_date->toDateTimeString());
        $this->assertNull($campaign->end_date);
        $this->get(route('campaigns.edit', $campaign))->assertOk()->assertSee(':disabled="permanent"', false);
        $this->patchJson(route('api.campaigns.update', $campaign), ['start_date' => '2026-10-14T11:25'])
            ->assertOk();
        $this->assertSame('2026-10-14 11:25:00', $campaign->fresh()->start_date->toDateTimeString());
        $this->assertNull($campaign->fresh()->end_date);
        Http::assertNothingSent();
    }

    public function test_validation_retry_normalizes_dates_without_crashing_on_malformed_values(): void
    {
        $campaign = Campaign::factory()->create(['start_date' => '2026-10-14 09:30:00', 'end_date' => '2026-10-14 17:45:30']);
        foreach (['campaigns.create', 'campaigns.edit'] as $name) {
            $parameters = $name === 'campaigns.edit' ? $campaign : [];
            $this->withSession(['_old_input' => [
                'start_date' => '2026-10-16 13:20:30', 'end_date' => '2026-10-17', 'is_permanent' => '0',
            ]])->get(route($name, $parameters))->assertOk()
                ->assertSee('value="2026-10-16T13:20:30"', false)->assertSee('value="2026-10-17T00:00"', false);
            $response = $this->withSession(['_old_input' => [
                'start_date' => ['invalid'], 'end_date' => 'invalid-date', 'is_permanent' => '0',
            ]])->get(route($name, $parameters))->assertOk();
            $this->assertMatchesRegularExpression('/name="start_date"[^>]*value=""/', $response->getContent());
            $this->assertMatchesRegularExpression('/name="end_date"[^>]*value=""/', $response->getContent());
        }
        Http::assertNothingSent();
    }
}
