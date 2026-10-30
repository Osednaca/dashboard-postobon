<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Media;
use App\Models\User;
use App\Services\Z2\Z2CampaignSyncService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

class CampaignActivationDiagnosticsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Storage::fake('public');
        config(['cache.default' => 'database', 'queue.default' => 'database', 'queue.connections.database.retry_after' => 2100]);
        $this->actingAs(User::factory()->admin()->create());
    }

    private function expectDiagnostics(string $message, int $id): void
    {
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('error')->once()->with($message, Mockery::on(fn ($context) => $context['campaign_id'] === $id));
        Log::shouldReceive('error')->once()->with($message, Mockery::on(fn ($context) => $context['campaign_id'] === $id));
        Log::shouldReceive('channel')->once()->with('diagnostics')->andReturn($logger);
    }

    public function test_expired_campaign_explains_why_and_keeps_draft(): void
    {
        $campaign = Campaign::factory()->draft()->create(['end_date' => now()->subMinute()]);
        $this->expectDiagnostics('No se pudo activar la campaña: validación', $campaign->id);
        $this->from(route('campaigns.index'))->post(route('campaigns.activate', $campaign))
            ->assertRedirect(route('campaigns.index'))
            ->assertSessionHas('error', 'La campaña ya terminó. Ajusta su período antes de activarla.');
        $this->assertSame('draft', $campaign->fresh()->status);
        $this->assertDatabaseCount('campaign_deliveries', 0);
        Http::assertNothingSent();
    }

    public function test_unconfigured_queue_explains_safe_operational_requirement(): void
    {
        config(['queue.default' => 'sync']);
        $campaign = Campaign::factory()->draft()->create();
        $this->expectDiagnostics('No se pudo activar la campaña: configuración', $campaign->id);
        $this->post(route('campaigns.activate', $campaign))
            ->assertSessionHas('error', 'La campaña requiere una cola database o redis con retry_after mayor a 1900 segundos. Contacta al administrador para revisar la configuración.');
        $this->assertSame('draft', $campaign->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_missing_media_is_explained(): void
    {
        $campaign = Campaign::factory()->draft()->create(['end_date' => null]);
        $this->expectDiagnostics('No se pudo activar la campaña: validación', $campaign->id);
        $this->post(route('campaigns.activate', $campaign))
            ->assertSessionHas('error', 'Selecciona al menos un video para activar la campaña.');
        Http::assertNothingSent();
    }

    public function test_cache_configuration_is_explained(): void
    {
        config(['cache.default' => 'array']);
        $campaign = Campaign::factory()->draft()->create();
        $this->expectDiagnostics('No se pudo activar la campaña: configuración', $campaign->id);
        $this->post(route('campaigns.activate', $campaign))
            ->assertSessionHas('error', 'La campaña requiere una caché compartida (database o redis). Contacta al administrador para revisar la configuración.');
        $this->assertSame('draft', $campaign->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_empty_explicit_audience_is_explained_without_publishing(): void
    {
        $campaign = Campaign::factory()->draft()->create(['target_devices' => [], 'end_date' => null]);
        Storage::disk('public')->put('media/example.mp4', 'fixture');
        $media = Media::factory()->video()->create(['file_path' => 'media/example.mp4', 'duration' => 10]);
        $campaign->media()->attach($media);
        $this->expectDiagnostics('No se pudo activar la campaña: validación', $campaign->id);
        $this->post(route('campaigns.activate', $campaign))
            ->assertSessionHas('error', 'Selecciona dispositivos disponibles para la campaña.');
        $this->assertSame('draft', $campaign->fresh()->status);
        $this->assertDatabaseCount('campaign_deliveries', 0);
        Http::assertNothingSent();
    }

    public function test_missing_duration_is_explained(): void
    {
        $campaign = Campaign::factory()->draft()->create(['end_date' => null]);
        $media = Media::factory()->video()->create(['file_path' => 'media/example.mp4', 'duration' => 0]);
        $campaign->media()->attach($media);
        $this->expectDiagnostics('No se pudo activar la campaña: validación', $campaign->id);
        $this->post(route('campaigns.activate', $campaign))
            ->assertSessionHas('error', 'Selecciona videos con duración conocida para reproducir la secuencia.');
        Http::assertNothingSent();
    }

    public function test_unexpected_error_is_logged_without_exposing_its_message_to_the_user(): void
    {
        $campaign = Campaign::factory()->draft()->create();
        $this->mock(Z2CampaignSyncService::class)->shouldReceive('activate')->once()->andThrow(new \RuntimeException('SECRET INTERNAL DETAIL'));
        $this->expectDiagnostics('Error inesperado al activar campaña', $campaign->id);
        $this->post(route('campaigns.activate', $campaign))->assertSessionHas('error', 'Ocurrió un error al activar la campaña.');
        Http::assertNothingSent();
    }

    public function test_technician_cannot_activate(): void
    {
        $campaign = Campaign::factory()->draft()->create();
        $this->actingAs(User::factory()->create(['role' => 'tecnico']));
        $this->post(route('campaigns.activate', $campaign))->assertForbidden();
        $this->assertDatabaseCount('campaign_deliveries', 0);
        Http::assertNothingSent();
    }
}
