<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignDelivery;
use App\Models\CampaignDeviceFile;
use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CampaignLifecyclePresentationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    public function test_finished_campaign_distinguishes_pending_removal_from_confirmed_removal(): void
    {
        $campaign = $this->campaign('finished');
        $this->delivery($campaign, 'removing', 'Video pendiente');
        $this->delivery($campaign, 'removed', 'Video retirado');
        $this->delivery($campaign, 'needs_review', 'Resultado incierto', '<script>alert(1)</script>');

        $response = $this->actingAs($campaign->creator)->get(route('campaigns.show', $campaign))
            ->assertOk()
            ->assertSee('Publicación y limpieza')
            ->assertSee('Borrado pendiente de confirmación')
            ->assertSee('Borrado confirmado')
            ->assertSee('Requiere revisión')
            ->assertSee('Finalizar una campaña no confirma el borrado.')
            ->assertSee('No se volverá a borrar por un índice incierto.')
            ->assertSee('<script>alert(1)</script>')
            ->assertDontSee('<script>alert(1)</script>', false);

        $this->assertSame(1, substr_count($response->getContent(), '>Borrado confirmado</span>'));
        Http::assertNothingSent();
    }

    public function test_retained_file_names_the_active_campaign_reference_without_claiming_removal(): void
    {
        $finished = $this->campaign('finished');
        $file = $this->delivery($finished, 'ready', 'Compartido')->file;
        $active = $this->campaign('active');
        CampaignDelivery::create([
            'campaign_id' => $active->id, 'campaign_device_file_id' => $file->id,
            'position' => 1, 'duration' => 10,
        ]);

        $this->actingAs($finished->creator)->get(route('campaigns.show', $finished))
            ->assertOk()->assertSee('Conservado por otra campaña')
            ->assertSee('Se conserva mientras otra campaña lo necesite.')
            ->assertDontSee('Borrado confirmado');
        Http::assertNothingSent();
    }

    public function test_pending_and_missing_delivery_records_do_not_imply_success(): void
    {
        $campaign = $this->campaign('active');
        $this->actingAs($campaign->creator)->get(route('campaigns.show', $campaign))
            ->assertOk()->assertSee('Aún no hay entregas registradas.')
            ->assertDontSee('Borrado confirmado');

        $this->delivery($campaign, 'pending', 'Esperando conexión', 'Dispositivo sin conexión');
        $this->delivery($campaign, 'installing', 'Instalando');
        $this->delivery($campaign, 'preserved', 'Video anterior');
        $this->actingAs($campaign->creator)->get(route('campaigns.show', $campaign))
            ->assertOk()->assertSee('Publicación pendiente')
            ->assertSee('Esperando confirmación de publicación')
            ->assertSee('Conservado por seguridad')
            ->assertSee('Dispositivo sin conexión')
            ->assertDontSee('Borrado confirmado');
        Http::assertNothingSent();
    }

    public function test_model_42_absence_is_not_presented_as_physical_sd_deletion(): void
    {
        $campaign = $this->campaign('finished');
        $delivery = $this->delivery($campaign, 'reported_absent', 'Video sin reporte');
        $delivery->file->update(['target_key' => 'z2:AABBCCDDEEFF']);

        $this->actingAs($campaign->creator)->get(route('campaigns.show', $campaign))
            ->assertOk()->assertSee('Ausente del reporte; borrado físico sin verificar')
            ->assertSee('no permite confirmar que el archivo haya desaparecido de la SD.')
            ->assertDontSee('Borrado confirmado');
        Http::assertNothingSent();
    }

    public function test_preparation_error_is_visible_before_any_delivery_exists(): void
    {
        $campaign = $this->campaign('scheduled');
        $campaign->forceFill(['lifecycle_error' => 'Falta la duración del video de lanzamiento.'])->save();

        $this->actingAs($campaign->creator)->get(route('campaigns.show', $campaign))
            ->assertOk()->assertSee('No se pudo preparar o actualizar la campaña')
            ->assertSee('Falta la duración del video de lanzamiento.')
            ->assertSee('Aún no hay entregas registradas.')
            ->assertDontSee('Borrado confirmado');
        Http::assertNothingSent();
    }

    public function test_expired_delivery_never_sent_is_not_presented_as_confirmed_deletion(): void
    {
        $campaign = $this->campaign('finished');
        $delivery = $this->delivery($campaign, 'removed', 'Video no enviado');
        $delivery->file->update(['confirmed_at' => null]);

        $this->actingAs($campaign->creator)->get(route('campaigns.show', $campaign))
            ->assertOk()->assertSee('Sin archivo pendiente de retirar')
            ->assertDontSee('Borrado confirmado');
        Http::assertNothingSent();
    }

    private function campaign(string $status): Campaign
    {
        return Campaign::factory()->create([
            'status' => $status, 'start_date' => now()->subDay(),
            'end_date' => $status === 'finished' ? now()->subMinute() : now()->addDay(),
            'created_by' => User::factory()->admin()->create()->id,
            'target_devices' => [], 'segment_groups' => [],
        ]);
    }

    private function delivery(Campaign $campaign, string $state, string $name, ?string $error = null): CampaignDelivery
    {
        $media = Media::create([
            'name' => $name, 'original_name' => $name.'.mp4', 'file_path' => 'media/'.hash('sha256', $name).'.mp4',
            'mime_type' => 'video/mp4', 'size' => 100, 'duration' => 10,
        ]);
        $file = CampaignDeviceFile::create([
            'target_key' => 'wl35:fixture-device', 'media_id' => $media->id,
            'identity_key' => hash('sha256', $name), 'filename' => $name.'.mp4',
            'state' => $state, 'owned' => $state !== 'preserved', 'last_error' => $error,
            'confirmed_at' => in_array($state, ['ready', 'removed']) ? now() : null,
        ]);

        return CampaignDelivery::create([
            'campaign_id' => $campaign->id, 'campaign_device_file_id' => $file->id,
            'position' => 1, 'duration' => 10,
        ]);
    }
}
