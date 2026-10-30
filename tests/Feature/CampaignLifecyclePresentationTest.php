<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignBaselineFile;
use App\Models\CampaignDelivery;
use App\Models\CampaignDeviceFile;
use App\Models\CampaignDevicePlayback;
use App\Models\Media;
use App\Models\User;
use App\Services\Campaigns\CampaignActivity;
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
            ->assertSee('deja de gestionar la secuencia sin apagar el equipo.')
            ->assertSee('el video actual puede continuar')
            ->assertDontSee('pausar o finalizar envía una orden de apagado')
            ->assertSee('No se volverá a borrar por un índice incierto.')
            ->assertSee('<script>alert(1)</script>')
            ->assertDontSee('<script>alert(1)</script>', false);

        $this->assertSame(1, substr_count($response->getContent(), '>Borrado confirmado</span>'));
        Http::assertNothingSent();
    }

    public function test_wl35_repeat_order_does_not_claim_mode_or_physical_playback_confirmed(): void
    {
        $campaign = $this->campaign('active');
        $this->delivery($campaign, 'ready', 'Video 35');
        $this->actingAs($campaign->creator)->get(route('campaigns.show', $campaign))
            ->assertOk()
            ->assertSee('se conserva el contenido anterior en la SD')
            ->assertSee('enviar la orden de repetición no confirma por sí solo el modo ni la reproducción física.')
            ->assertSee('un video que ya estaba en la SD puede volver a mostrarse')
            ->assertDontSee('Repetición individual confirmada');
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

    public function test_pending_delivery_displays_observed_activity_and_device_errors_without_claiming_a_worker_failure(): void
    {
        $campaign = $this->campaign('active');
        $delivery = $this->delivery($campaign, 'pending', 'En espera');
        CampaignDevicePlayback::create(['target_key' => $delivery->file->target_key, 'last_error' => 'No se confirmó la detención o reanudación del dispositivo.']);
        $activity = app(CampaignActivity::class);
        $this->actingAs($campaign->creator)->get(route('campaigns.show', $campaign))->assertOk()
            ->assertSee('No se ha observado actividad reciente del programador')
            ->assertSee('Aún no se ha observado un intento reciente para este dispositivo.')
            ->assertSee('Error del dispositivo: No se confirmó la detención o reanudación del dispositivo.')
            ->assertDontSee('worker detenido');
        $activity->tick();
        $activity->deviceStarted($delivery->file->target_key);
        $this->actingAs($campaign->creator)->get(route('campaigns.show', $campaign))->assertOk()
            ->assertSee('Última actividad observada del programador de campañas:')
            ->assertSee('Último intento observado:')
            ->assertSee('Esta observación no confirma que se haya publicado el video.');
        Http::assertNothingSent();
    }

    public function test_legacy_error_with_url_or_secret_is_not_displayed(): void
    {
        $campaign = $this->campaign('active');
        $this->delivery($campaign, 'pending', 'Video', 'https://example.test/?token=secret');
        $campaign->update(['lifecycle_error' => 'password=secret']);
        $this->actingAs($campaign->creator)->get(route('campaigns.show', $campaign))->assertOk()
            ->assertSee('No se pudo procesar el dispositivo. Revisa los registros del servidor.')
            ->assertDontSee('https://example.test/')
            ->assertDontSee('password=secret');
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

    public function test_previous_content_and_accepted_restoration_do_not_claim_physical_playback(): void
    {
        $campaign = $this->campaign('finished');
        $file = $this->delivery($campaign, 'preserved', 'Anterior')->file;
        $file->update(['target_key' => 'z2:AABBCCDDEEFF']);
        CampaignDevicePlayback::create(['target_key' => $file->target_key, 'baseline' => ['type' => 'z2', 'files' => [$file->filename], 'selection' => $file->filename], 'restore_state' => 'restored']);
        $this->actingAs($campaign->creator)->get(route('campaigns.show', $campaign))->assertOk()
            ->assertSee('Contenido conservado por seguridad')
            ->assertSee('Recuperación de contenido anterior solicitada')
            ->assertSee('esto no confirma su reproducción física')
            ->assertSee('estaba en el dispositivo');
        Http::assertNothingSent();
    }

    public function test_pending_restoration_error_is_visible_and_escaped(): void
    {
        $campaign = $this->campaign('finished');
        $file = $this->delivery($campaign, 'preserved', 'Anterior')->file;
        CampaignDevicePlayback::create(['target_key' => $file->target_key, 'baseline' => ['type' => 'legacy'], 'restore_state' => 'pending', 'restore_error' => '<script>error</script>']);
        $this->actingAs($campaign->creator)->get(route('campaigns.show', $campaign))->assertOk()
            ->assertSee('Recuperación del contenido anterior pendiente')
            ->assertSee('no se puede reconstruir la selección previa')
            ->assertSee('<script>error</script>')->assertDontSee('<script>error</script>', false);
        Http::assertNothingSent();
    }

    public function test_preparing_backup_is_visible_before_publication(): void
    {
        $campaign = $this->campaign('active');
        $file = $this->delivery($campaign, 'pending', 'Pendiente')->file;
        CampaignDevicePlayback::create([
            'target_key' => $file->target_key,
            'baseline' => ['type' => 'z2', 'files' => ['anterior.mp4'], 'selection' => 'anterior.mp4'],
            'restore_state' => 'preparing',
            'restore_error' => 'No se confirmó la detención o reanudación del dispositivo.',
        ]);

        $this->actingAs($campaign->creator)->get(route('campaigns.show', $campaign))->assertOk()
            ->assertSee('Preparando respaldo del contenido anterior')
            ->assertSee('No se confirmó la detención o reanudación del dispositivo.')
            ->assertSee('Publicación pendiente')
            ->assertDontSee('Contenido anterior conservado en la SD');
        Http::assertNothingSent();
    }

    public function test_baseline_file_statuses_show_current_session_and_never_claim_physical_deletion_for_42(): void
    {
        $campaign = $this->campaign('active');
        $file = $this->delivery($campaign, 'pending', 'Pendiente')->file;
        $file->update(['target_key' => 'z2:AABBCCDDEEFF']);
        $session = 'b1676e5f-ea81-4139-8338-fcb41d5a73bf';
        $playback = CampaignDevicePlayback::create([
            'target_key' => $file->target_key,
            'baseline' => ['type' => 'z2', 'session_id' => $session, 'files' => ['anterior.mp4']],
            'restore_state' => 'preparing',
        ]);
        $attributes = [
            'playback_id' => $playback->id, 'session_id' => $session, 'position' => 1,
            'filename' => 'anterior.mp4', 'backup_disk' => 'local', 'backup_path' => 'backup/test',
            'backup_hash' => str_repeat('a', 64), 'backup_size' => 10, 'state' => 'removed',
        ];
        CampaignBaselineFile::create($attributes);
        CampaignBaselineFile::create(array_merge($attributes, ['session_id' => 'ff10560c-4ef8-42b0-a51e-5a715fc008a3', 'filename' => 'sesion-antigua.mp4']));

        $this->actingAs($campaign->creator)->get(route('campaigns.show', $campaign))->assertOk()
            ->assertSee('anterior.mp4')
            ->assertSee('Retiro procesado')
            ->assertSee('Ausente del reporte; borrado físico de SD sin verificar.')
            ->assertDontSee('sesion-antigua.mp4')
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
