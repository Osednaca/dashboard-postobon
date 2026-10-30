<?php

namespace App\Services\Campaigns;

use App\Models\CampaignDelivery;
use App\Models\CampaignDeviceFile;
use App\Models\CampaignDevicePlayback;
use App\Services\Fleet\DeviceMutationLock;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

/** Called only while the physical-device lock is held. */
class CampaignPlaybackRestoration
{
    public function __construct(private CampaignDeviceTransport $transport) {}

    public function begin(string $target, Collection $files, bool $hasWinner): ?CampaignDevicePlayback
    {
        $playback = CampaignDevicePlayback::where('target_key', $target)->first();
        if ($playback?->baseline !== null && $playback->baseline_closed_at === null) {
            if (! isset($playback->baseline['session_id'])) {
                $baseline = $playback->baseline;
                $baseline['session_id'] = (string) Str::uuid();
                $playback->update(['baseline' => $baseline]);
            }
            if ($hasWinner && $playback->restore_state !== 'managed') {
                $playback->update(['restore_state' => 'managed', 'restore_error' => null]);
            }

            return $playback;
        }
        $unfinished = $files->whereNotIn('state', ['removed', 'preserved', 'reported_absent']);
        if (! $hasWinner && $unfinished->isEmpty() && ! $playback?->powered_off && ! $playback?->campaign_id) {
            return $playback;
        }
        $legacy = $unfinished->contains(fn ($file) => $file->state !== 'pending') || $playback?->campaign_id || $playback?->powered_off;
        if (! $hasWinner && ! $legacy) {
            return $playback;
        }
        $playback = DeviceMutationLock::withBaselineLock(function () use ($legacy, $target, $hasWinner): CampaignDevicePlayback {
            $baseline = $legacy ? ['type' => 'legacy', 'selection' => null] : $this->transport->captureBaseline($target);
            $baseline['session_id'] = (string) Str::uuid();

            return CampaignDevicePlayback::updateOrCreate(['target_key' => $target], [
                'baseline' => $baseline, 'baseline_closed_at' => null,
                'restore_state' => $hasWinner ? 'managed' : 'pending', 'restore_error' => null,
            ]);
        });
        if ($legacy) {
            // Old ownership flags did not distinguish reused content from new uploads.
            // Do not retroactively claim those files were absent before the campaign.
            $unfinished->where('state', '!=', 'removing')->each(fn ($file) => $file->update(['owned' => false]));
        }

        return $playback;
    }

    public function restore(?CampaignDevicePlayback $playback): void
    {
        if ($playback?->baseline === null || $playback->baseline_closed_at !== null) {
            return;
        }
        try {
            if (! in_array($playback->restore_state, ['restored', 'empty', 'unavailable'], true)) {
                $playback->update(['restore_state' => 'pending']);
                $baseline = $playback->baseline;
                if (($baseline['type'] ?? null) === 'legacy') {
                    $playback->update(['restore_state' => 'unavailable', 'restore_error' => 'No se registró la reproducción anterior a esta campaña. Se conserva el contenido cuya propiedad no puede determinarse.']);
                } elseif (($baseline['count'] ?? count($baseline['files'] ?? [])) === 0) {
                    $playback->update(['restore_state' => 'empty', 'restore_error' => null]);
                } elseif (($baseline['selection'] ?? null) === null) {
                    $playback->update(['restore_state' => 'unavailable', 'restore_error' => 'Se conservan los videos anteriores, pero el dispositivo no informó una selección válida para recuperarla automáticamente.']);
                } else {
                    if (! $this->transport->restoreBaseline($playback->target_key, $baseline)) {
                        return;
                    }
                    $playback->update(['restore_state' => 'restored', 'restore_error' => null, 'last_error' => null]);
                }
            }
            $unresolved = CampaignDeviceFile::where('target_key', $playback->target_key)->whereIn('state', ['pending', 'installing', 'removing', 'ready', 'needs_review'])->exists();
            $resumable = CampaignDelivery::whereNull('released_at')->whereHas('file', fn ($q) => $q->where('target_key', $playback->target_key))
                ->whereHas('campaign', fn ($q) => $q->whereIn('status', ['active', 'paused', 'scheduled'])->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>', now())))->exists();
            if (! $unresolved && ! $resumable) {
                $playback->update(['baseline_closed_at' => now()]);
            }
        } catch (Throwable $e) {
            $playback->update(['restore_state' => 'pending', 'restore_error' => CampaignError::publicMessage($e)]);
            throw $e;
        }
    }
}
