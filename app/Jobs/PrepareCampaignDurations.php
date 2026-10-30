<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\Media;
use App\Services\Campaigns\CampaignLifecycle;
use App\Services\Fleet\VideoDurationProbe;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class PrepareCampaignDurations implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1900;

    public int $tries = 1;

    public function __construct(public int $campaignId, public string $token, public string $fingerprint)
    {
        $this->onQueue('campaigns');
    }

    public function handle(VideoDurationProbe $probe, CampaignLifecycle $lifecycle): void
    {
        $campaign = Campaign::find($this->campaignId);
        if (! $this->matches($campaign, $lifecycle)) {
            $this->failPreparation('La campaña cambió o terminó durante la preparación. Revisa sus datos antes de activarla.');

            return;
        }
        foreach ($campaign->media()->get() as $media) {
            if (! $this->matches(Campaign::find($this->campaignId), $lifecycle)) {
                $this->failPreparation('La campaña cambió o terminó durante la preparación. Revisa sus datos antes de activarla.');

                return;
            }
            if ($media->duration > 0) {
                continue;
            }
            $path = $media->file_path;
            $size = $media->size;
            $updated = $media->getRawOriginal('updated_at');
            $duration = $probe->recover($media);
            if ($duration === null) {
                $this->failPreparation('No se pudo obtener la duración del video «'.$media->name.'». Verifica el archivo y vuelve a intentar.');

                return;
            }
            // Do not replace a newly supplied duration or attach it to a changed source.
            $saved = Media::whereKey($media->id)->where('file_path', $path)->where('size', $size)->where('updated_at', $updated)
                ->where(fn ($query) => $query->whereNull('duration')->orWhere('duration', '<=', 0))
                ->update(['duration' => $duration]);
            if (! $saved && ! (Media::find($media->id)?->duration > 0)) {
                $this->failPreparation('El video «'.$media->name.'» cambió durante la preparación. Vuelve a intentarlo.');

                return;
            }
        }
        DB::transaction(function () use ($lifecycle): void {
            $campaign = Campaign::whereKey($this->campaignId)->lockForUpdate()->first();
            if (! $this->matches($campaign, $lifecycle)) {
                if ($campaign && $campaign->duration_preparation_token === $this->token) {
                    $campaign->update(['duration_preparation_state' => 'failed', 'lifecycle_error' => 'La campaña cambió durante la preparación. Revisa los datos y vuelve a activarla.']);
                }

                return;
            }
            try {
                $campaign->update(['duration_preparation_token' => null, 'duration_preparation_state' => null]);
                $lifecycle->activate($campaign);
            } catch (Throwable $e) {
                $message = $e instanceof ValidationException
                    ? collect($e->errors())->flatten()->first()
                    : 'No se pudo preparar la campaña. Revisa sus videos, fechas y dispositivos antes de volver a intentarlo.';
                Log::warning('No se pudo finalizar la preparación de campaña.', ['campaign_id' => $campaign->id, 'exception_type' => $e::class]);
                $campaign->update(['duration_preparation_token' => $this->token, 'duration_preparation_state' => 'failed', 'lifecycle_error' => $message]);
            }
        });
    }

    public function failed(?Throwable $exception): void
    {
        $this->failPreparation('No se pudo preparar la duración de los videos. Revisa la disponibilidad del archivo y vuelve a intentarlo.');
    }

    private function matches(?Campaign $campaign, CampaignLifecycle $lifecycle): bool
    {
        return $campaign !== null && $campaign->duration_preparation_token === $this->token
            && $campaign->duration_preparation_state === 'pending'
            && ! $campaign->end_date?->lte(now())
            && hash_equals($this->fingerprint, $lifecycle->durationFingerprint($campaign));
    }

    private function failPreparation(string $message): void
    {
        Campaign::whereKey($this->campaignId)->where('duration_preparation_token', $this->token)
            ->where('duration_preparation_state', 'pending')
            ->update(['duration_preparation_state' => 'failed', 'lifecycle_error' => $message]);
    }
}
