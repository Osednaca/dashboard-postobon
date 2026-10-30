<?php

namespace App\Services\Campaigns;

use App\Models\CampaignDeviceFile;
use App\Models\CampaignDevicePlayback;
use App\Models\Wl35DeviceMedia;
use App\Services\Fleet\UnifiedFleetClient;
use RuntimeException;

/** Called only under the physical device lock. */
class CampaignWl35Baseline
{
    public function __construct(private CampaignBaselineBackup $backups, private CampaignBaselineTransition $transition, private UnifiedFleetClient $fleet) {}

    public function prepare(CampaignDevicePlayback $playback, bool $hasWinner): bool
    {
        $baseline = $playback->baseline;
        if (($baseline['type'] ?? null) !== 'wl35') {
            if (! $hasWinner) {
                return true;
            }
            throw new RuntimeException('No se conoce el contenido anterior del equipo 35; requiere revisión.');
        }
        $files = $this->backups->files($playback);
        if ($files->contains(fn ($f) => ! in_array($f->state, ['backed_up', 'removing', 'removed', 'restoring', 'restored', 'needs_review'], true))) {
            throw new RuntimeException('El contenido anterior del equipo 35 requiere revisión antes de continuar.');
        }
        if ($files->contains(fn ($f) => in_array($f->state, ['removing', 'restoring', 'needs_review'], true))) {
            $files->whereIn('state', ['removing', 'restoring'])->each(fn ($f) => $f->update([
                'state' => 'needs_review', 'last_error' => 'La operación anterior del equipo 35 es ambigua y requiere revisión.',
            ]));
            throw new RuntimeException('El contenido anterior del equipo 35 requiere revisión antes de continuar.');
        }
        if (! $files->contains(fn ($f) => in_array($f->state, ['removed', 'restored'], true)) || ($baseline['t15_reconciled'] ?? false)) {
            return true;
        }
        if ($files->count() !== ($baseline['count'] ?? null)) {
            throw new RuntimeException('El respaldo anterior del equipo 35 está incompleto y requiere revisión.');
        }
        foreach ($files as $file) {
            $this->backups->verify($file);
        }
        if ($files->contains('state', 'removed')) {
            // T15 already removed content. Finish that recovery before enabling the new policy.
            // Never retry ambiguous 35 writes, and verify the durable source on each attempt.
            $playback->update(['restore_state' => 'pending']);
            $this->transition->restorePrevious($playback, fn () => true);

            return false;
        }
        $deviceId = substr($playback->target_key, 5);
        $device = collect($this->fleet->getFleet()['devices'] ?? [])->first(fn ($d) => ($d['type'] ?? null) === 'wl35' && ($d['id'] ?? null) === $deviceId);
        if (! $device || ! ($device['online'] ?? false) || ! ($device['session_ready'] ?? false) || ($device['uploading'] ?? false)) {
            throw new RuntimeException('El equipo 35 no está disponible para procesar la campaña.');
        }
        $previousIndices = [];
        $selected = null;
        foreach ($files as $file) {
            $identity = $file->operation['current_identity'] ?? $file->upload_identity;
            if (! is_string($identity) || $identity === '') {
                throw new RuntimeException('El contenido anterior del equipo 35 requiere revisión antes de continuar.');
            }
            $mappings = Wl35DeviceMedia::where('device_id', $deviceId)->where('upload_identity', $identity)->get();
            $mapping = $mappings->count() === 1 ? $mappings->first() : null;
            if (! $mapping || $mapping->video_index < 1 || $mapping->video_index > (int) ($device['video_count'] ?? 0) || in_array($mapping->video_index, $previousIndices, true)) {
                throw new RuntimeException('El contenido anterior del equipo 35 requiere revisión antes de continuar.');
            }
            $previousIndices[] = $mapping->video_index;
            if ($file->original_index === ($baseline['selection'] ?? null)) {
                $selected = $mapping;
            }
        }
        $added = CampaignDeviceFile::where('target_key', $playback->target_key)->where('owned', true)->where('state', 'ready')->pluck('video_index')->filter()->unique();
        if ($added->intersect($previousIndices)->isNotEmpty() || ($device['video_count'] ?? null) !== count($previousIndices) + $added->count() || (($baseline['selection'] ?? null) !== null && ! $selected)) {
            throw new RuntimeException('El contenido anterior del equipo 35 requiere revisión antes de continuar.');
        }
        $baseline['selection'] = $selected?->video_index;
        $baseline['upload_identity'] = $selected?->upload_identity;
        $baseline['t15_reconciled'] = true;
        $playback->update(['baseline' => $baseline, 'restore_error' => null]);

        return true;
    }
}
