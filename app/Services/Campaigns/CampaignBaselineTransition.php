<?php

namespace App\Services\Campaigns;

use App\Models\CampaignBaselineFile;
use App\Models\CampaignDelivery;
use App\Models\CampaignDeviceFile;
use App\Models\CampaignDevicePlayback;
use App\Models\Wl35DeviceMedia;
use RuntimeException;

/** Reconcile one physical operation at a time while the device lock is held. */
class CampaignBaselineTransition
{
    public function __construct(private CampaignBaselineBackup $backups, private CampaignDeviceTransport $transport) {}

    public function prepare(CampaignDevicePlayback $playback): bool
    {
        $playback->update(['restore_state' => 'preparing']);
        try {
            if (! $this->backups->prepare($playback)) {
                return false;
            }
        } catch (RuntimeException $e) {
            $message = $e->getMessage();
            if (! str_starts_with($message, 'No se puede identificar y respaldar el video anterior ')
                && ! in_array($message, [
                    'Configura un disco local persistente para respaldar el contenido anterior antes de iniciar la campaña.',
                    'No se conoce el contenido anterior; no se puede retirarlo sin respaldo.',
                    'La copia de seguridad del video anterior falta o está dañada.',
                    'El inventario anterior cambió durante la preparación del respaldo.',
                    'No se pudo encontrar el archivo de video seleccionado.',
                ], true)) {
                $message = 'No se pudo respaldar el contenido anterior. Revisa los registros del servidor.';
            }
            $playback->update(['restore_error' => CampaignError::safeStored($message)]);
            throw $e;
        }
        $playback->update(['restore_state' => 'managed', 'restore_error' => null]);

        return true;
    }

    public function removePrevious(CampaignDevicePlayback $playback, callable $stillActive): bool
    {
        foreach ($this->backups->files($playback) as $file) {
            if ($file->state === 'needs_review') {
                throw new RuntimeException('El contenido anterior requiere revisión antes de continuar.');
            }
            if ($file->state === 'removing') {
                $this->transport->removeBaseline($file);
                $this->invalidateRemovedAliases($playback, $file);

                return false;
            }
            if (in_array($file->state, ['backed_up', 'restored'], true) && ! $this->usedByActiveCampaign($playback, $file)) {
                // Check all copies before every irreversible removal, not just the first.
                foreach ($this->backups->files($playback) as $backup) {
                    $this->backups->verify($backup);
                }
                if (! $stillActive() || $this->usedByActiveCampaign($playback, $file)) {
                    return false;
                }
                $this->transport->removeBaseline($file);
                $this->invalidateRemovedAliases($playback, $file);

                return false;
            }
            if ($file->state === 'restoring') {
                $this->transport->restoreBaselineFile($file, $this->backups->verify($file));

                return false;
            }
        }

        return true;
    }

    private function invalidateRemovedAliases(CampaignDevicePlayback $playback, CampaignBaselineFile $file): void
    {
        $file->refresh();
        if ($file->state !== 'removed') {
            return;
        }
        $query = CampaignDeviceFile::where('target_key', $playback->target_key)->where('state', 'ready');
        if (str_starts_with($playback->target_key, 'z2:')) {
            $query->where('filename', $file->operation['restored_filename'] ?? $file->filename);
        } else {
            $query->where('evidence->upload_identity', $file->operation['restored_upload_identity'] ?? $file->upload_identity);
        }
        $query->update(['state' => 'pending', 'owned' => false, 'confirmed_at' => null, 'evidence' => null]);
    }

    public function restorePrevious(CampaignDevicePlayback $playback, callable $stillInactive): bool
    {
        $files = $this->backups->files($playback);
        foreach ($files as $file) {
            if ($file->state === 'needs_review') {
                throw new RuntimeException('El contenido anterior requiere revisión antes de continuar.');
            }
            if ($file->state === 'removing') {
                $playback->update(['restore_state' => 'pending']);
                $this->transport->removeBaseline($file);

                return false;
            }
            if (in_array($file->state, ['removed', 'restoring'], true)) {
                $playback->update(['restore_state' => 'pending']);
                $path = $this->backups->verify($file);
                if (! $stillInactive()) {
                    return false;
                }
                $this->transport->restoreBaselineFile($file, $path);

                return false;
            }
        }

        $baseline = $playback->baseline;
        if (($baseline['selection'] ?? null) !== null && $playback->restore_state !== 'restored') {
            $selected = ($baseline['type'] ?? null) === 'z2'
                ? $files->firstWhere('filename', $baseline['selection'])
                : $files->firstWhere('original_index', $baseline['selection']);
            if (! $selected) {
                throw new RuntimeException('La selección anterior no está disponible para recuperarla.');
            }
            if ($baseline['type'] === 'z2') {
                $baseline['selection'] = $selected->operation['restored_filename'] ?? $selected->filename;
            } else {
                $identity = $selected->operation['restored_upload_identity'] ?? $selected->upload_identity;
                $mapping = Wl35DeviceMedia::where('device_id', substr($playback->target_key, 5))->where('upload_identity', $identity)->first();
                if (! $mapping) {
                    throw new RuntimeException('La identidad de la selección anterior del equipo 35 cambió.');
                }
                $baseline['selection'] = $mapping->video_index;
                $baseline['upload_identity'] = $identity;
            }
            if (! $stillInactive()) {
                return false;
            }
            $this->transport->restoreBaseline($playback->target_key, $baseline);
            $playback->update(['restore_state' => 'restored', 'restore_error' => null]);
        }

        return true;
    }

    private function usedByActiveCampaign(CampaignDevicePlayback $playback, CampaignBaselineFile $file): bool
    {
        return CampaignDelivery::whereNull('released_at')
            ->whereHas('campaign', fn ($q) => $q->where('status', 'active')->whereNull('deleted_at')
                ->where(fn ($q) => $q->whereNull('start_date')->orWhere('start_date', '<=', now()))
                ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>', now())))
            ->whereHas('file', fn ($q) => $q->where('target_key', $playback->target_key)
                ->where(fn ($q) => $q->where('media_id', $file->media_id)->orWhere('filename', $file->filename)))
            ->exists();
    }
}
