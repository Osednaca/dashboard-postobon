<?php

namespace App\Services\Campaigns;

use App\Jobs\PrepareCampaignDurations;
use App\Jobs\ProcessCampaignDeviceJob;
use App\Models\Campaign;
use App\Models\CampaignDelivery;
use App\Models\CampaignDeviceFile;
use App\Models\CampaignDevicePlayback;
use App\Models\Device;
use App\Services\CampaignTargetCatalog;
use App\Services\Fleet\MediaPreviewSource;
use App\Services\Fleet\RetiredDeviceJobExclusions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class CampaignLifecycle
{
    public function __construct(private CampaignDeviceTransport $transport) {}

    public function assertQueueConfiguration(): void
    {
        if (! in_array(config('cache.stores.'.config('cache.default').'.driver'), ['database', 'redis'])) {
            throw new CampaignConfigurationException('La campaña requiere una caché compartida (database o redis). Contacta al administrador para revisar la configuración.');
        }
        $connection = config('queue.default');
        if (! in_array(config('queue.connections.'.$connection.'.driver'), ['database', 'redis']) || (int) config('queue.connections.'.$connection.'.retry_after') <= 1900) {
            throw new CampaignConfigurationException('La campaña requiere una cola database o redis con retry_after mayor a 1900 segundos. Contacta al administrador para revisar la configuración.');
        }
    }

    public static function lockName(string $target): string
    {
        return str_starts_with($target, 'wl35:') ? 'device-video-removal:wl35:'.hash('sha256', substr($target, 5)) : 'campaign-device:'.hash('sha256', $target);
    }

    public function activate(Campaign $campaign): bool
    {
        $this->assertQueueConfiguration();
        $campaign->refresh();
        if ($campaign->end_date?->lte(now())) {
            throw ValidationException::withMessages(['end_date' => 'La campaña ya terminó. Ajusta su período antes de activarla.']);
        }
        if ($campaign->media()->count() === 0) {
            throw ValidationException::withMessages(['media_ids' => 'Selecciona al menos un video para activar la campaña.']);
        }
        $media = $campaign->media()->get();
        if ($media->contains(fn ($item) => ! str_starts_with($item->mime_type, 'video/'))) {
            throw ValidationException::withMessages(['media_ids' => 'Selecciona únicamente videos para la campaña.']);
        }
        if ($media->contains(fn ($item) => ! $item->duration || $item->duration < 1)) {
            $token = DB::transaction(function () use ($campaign): ?string {
                $locked = Campaign::whereKey($campaign->id)->lockForUpdate()->firstOrFail();
                if ($locked->duration_preparation_state === 'pending') {
                    return null;
                }
                $token = (string) Str::uuid();
                $locked->update(['duration_preparation_token' => $token, 'duration_preparation_state' => 'pending', 'lifecycle_error' => null]);

                return $token;
            });
            if ($token === null) {
                return false;
            }
            try {
                PrepareCampaignDurations::dispatch($campaign->id, $token, $this->durationFingerprint($campaign->fresh()))->afterCommit();
            } catch (Throwable $e) {
                $campaign->update(['duration_preparation_state' => 'failed', 'lifecycle_error' => 'No se pudo iniciar la preparación de los videos. Inténtalo nuevamente.']);
                throw $e;
            }

            return false;
        }
        $previous = $campaign->status;
        $campaign->update(['status' => 'scheduled']);
        try {
            $this->snapshot($campaign);
        } catch (Throwable $e) {
            $campaign->update(['status' => $previous]);
            throw $e;
        }
        $campaign->update(['status' => $campaign->start_date?->isFuture() ? 'scheduled' : 'active', 'lifecycle_error' => null, 'duration_preparation_token' => null, 'duration_preparation_state' => null]);

        return true;
    }

    public function durationFingerprint(Campaign $campaign): string
    {
        $attributes = $campaign->getAttributes();
        unset($attributes['duration_preparation_token'], $attributes['duration_preparation_state'], $attributes['lifecycle_error']);
        $media = $campaign->media()->get()->map(fn ($item) => [$item->id, $item->file_path, $item->mime_type, $item->size, $item->pivot->order])->all();
        $legacy = $campaign->target_devices === null ? $campaign->deviceCampaigns()->orderBy('device_id')->pluck('device_id')->all() : [];

        return hash('sha256', json_encode([$attributes, $media, $legacy], JSON_THROW_ON_ERROR));
    }

    public function finish(Campaign $campaign): void
    {
        $campaign->update(['status' => 'finished', 'duration_preparation_token' => null, 'duration_preparation_state' => null]);
    }

    public function snapshot(Campaign $campaign): void
    {
        $media = $campaign->media()->get();
        $sources = [];
        foreach ($media as $item) {
            if (! str_starts_with($item->mime_type, 'video/') || $item->duration < 1) {
                throw ValidationException::withMessages(['media_ids' => 'Selecciona videos con duración conocida para reproducir la secuencia.']);
            }
            $sources[$item->id] = app(MediaPreviewSource::class)->resolve($item);
            if (! $sources[$item->id]) {
                throw ValidationException::withMessages(['media_ids' => 'Un video seleccionado no está disponible.']);
            }
        }
        $targets = $campaign->target_devices;
        if ($targets === null) {
            $targets = Device::whereIn('id', $campaign->deviceCampaigns()->pluck('device_id'))->get()->map(fn ($d) => CampaignTargetCatalog::z2Key($d->mac_address))->unique()->all();
        }
        [$targets] = app(RetiredDeviceJobExclusions::class)->filter('campaign', (string) $campaign->id, $targets);
        $targets = app(CampaignTargetCatalog::class)->validatedKeys($targets);
        if ($targets === []) {
            throw ValidationException::withMessages(['target_devices' => 'Selecciona dispositivos disponibles para la campaña.']);
        }
        // Old targets participate in the same transaction so an audience edit cannot race cleanup.
        $lockTargets = array_unique(array_merge($targets, $campaign->deliveries()->with('file')->get()->pluck('file.target_key')->all()));
        sort($lockTargets);
        $locks = [];
        try {
            foreach ($lockTargets as $target) {
                $lock = Cache::lock(self::lockName($target), 2000);
                if (! $lock->get()) {
                    throw new CampaignDeviceBusyException;
                }
                $locks[] = $lock;
            }
            DB::transaction(function () use ($campaign, $targets, $media, $sources): void {
                $current = [];
                foreach ($targets as $target) {
                    foreach ($media as $position => $item) {
                        $source = $sources[$item->id];
                        $identity = $source['kind'] === 'private_cloud' ? 'cloud:'.rawurldecode(basename($source['path'])) : 'source:'.$item->file_path;
                        $file = CampaignDeviceFile::firstOrCreate(['target_key' => $target, 'identity_key' => hash('sha256', $identity)], ['media_id' => $item->id, 'filename' => $source['kind'] === 'private_cloud' ? rawurldecode(basename($source['path'])) : 'campaign-'.substr(hash('sha256', $identity), 0, 24).'.mp4']);
                        if ($file->state === 'removing' || (str_starts_with($target, 'z2:') && CampaignDeviceFile::where('target_key', $target)->where('filename', $file->filename)->where('state', 'removing')->exists())) {
                            throw ValidationException::withMessages(['media_ids' => 'Este archivo aún se está retirando. Espera su confirmación antes de activarlo.']);
                        }
                        if (in_array($file->state, ['removed', 'preserved', 'reported_absent'])) {
                            $file->update(['state' => 'pending', 'owned' => false, 'requested_at' => null, 'confirmed_at' => null, 'evidence' => null]);
                        }
                        $delivery = CampaignDelivery::updateOrCreate(['campaign_id' => $campaign->id, 'campaign_device_file_id' => $file->id], ['position' => $position + 1, 'duration' => $item->duration, 'released_at' => null]);
                        $current[] = $delivery->id;
                    }
                }
                $campaign->deliveries()->whereNotIn('id', $current)->whereNull('released_at')->update(['released_at' => now()]);
            });
        } finally {
            foreach (array_reverse($locks) as $lock) {
                $lock->release();
            }
        }
    }

    public function tick(): void
    {
        $this->assertQueueConfiguration();
        // Paused campaigns also expire; permanent campaigns have no deadline.
        Campaign::whereIn('status', ['active', 'paused', 'scheduled'])->whereNotNull('end_date')->where('end_date', '<=', now())->update(['status' => 'finished', 'duration_preparation_token' => null, 'duration_preparation_state' => null]);
        foreach (Campaign::where('status', 'scheduled')->whereNull('duration_preparation_state')->where(fn ($q) => $q->whereNull('start_date')->orWhere('start_date', '<=', now()))->get() as $campaign) {
            try {
                $this->activate($campaign);
            } catch (CampaignDeviceBusyException $e) {
                // A device job owns this lock during normal publication; retry on the next tick.
            } catch (Throwable $e) {
                $this->recordCampaignError($campaign, CampaignError::publicMessage($e));
            }
        }
        // Existing active campaigns and edits are reconciled without discarding old delivery evidence.
        foreach (Campaign::where('status', 'active')->get() as $campaign) {
            try {
                if ($campaign->duration_preparation_state !== null) {
                    continue;
                }
                if ($campaign->media()->where(fn ($q) => $q->whereNull('duration')->orWhere('duration', '<=', 0))->exists()) {
                    $this->activate($campaign);

                    continue;
                }
                $this->snapshot($campaign);
            } catch (CampaignDeviceBusyException $e) {
                // Reconciliation must wait for the in-flight device operation.
            } catch (Throwable $e) {
                $this->recordCampaignError($campaign, CampaignError::publicMessage($e));
            }
        }
        CampaignDeviceFile::whereNotIn('state', ['removed', 'preserved', 'reported_absent'])->select('target_key')->distinct()->pluck('target_key')->merge(CampaignDevicePlayback::whereNotNull('campaign_id')->orWhere('powered_off', true)->orWhere(fn ($q) => $q->whereNotNull('baseline')->whereNull('baseline_closed_at'))->pluck('target_key'))->unique()->each(fn ($target) => ProcessCampaignDeviceJob::dispatch($target));
        app(CampaignActivity::class)->tick();
    }

    private function recordCampaignError(Campaign $campaign, string $error): void
    {
        $campaign->update(['lifecycle_error' => $error]);
        $campaign->deliveries()->get()->each(fn ($d) => $d->file->update(['last_error' => $error]));
        Log::warning('CampaÃ±a pendiente de reconciliaciÃ³n', ['campaign_id' => $campaign->id, 'error' => $error]);
    }

    private function references(CampaignDeviceFile $file)
    {
        $physicalIds = CampaignDeviceFile::where('target_key', $file->target_key)->where(function ($q) use ($file): void {
            $q->whereKey($file->id);
            if (str_starts_with($file->target_key, 'z2:') && $file->filename) {
                $q->orWhere('filename', $file->filename);
            } elseif ($file->evidence['upload_identity'] ?? null) {
                $q->orWhere('evidence->upload_identity', $file->evidence['upload_identity']);
            }
        })->pluck('id');

        return CampaignDelivery::whereIn('campaign_device_file_id', $physicalIds)->whereNull('released_at')->whereHas('campaign', fn ($q) => $q->whereNull('deleted_at')->where(fn ($q) => $q->where('status', 'active')->orWhere(fn ($q) => $q->where('status', 'scheduled')->where(fn ($q) => $q->whereNull('start_date')->orWhere('start_date', '<=', now()))))->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>', now())));
    }

    private function wantedNow(CampaignDeviceFile $file): bool
    {
        return $this->references($file)->with('campaign.media')->get()->contains(function ($delivery) use ($file): bool {
            $campaign = $delivery->campaign;
            if ($campaign->status !== 'active' || $campaign->start_date?->isFuture() || $campaign->end_date?->lte(now())) {
                return false;
            }
            if ($campaign->target_devices !== null && ! in_array($file->target_key, $campaign->target_devices, true)) {
                return false;
            }

            return $campaign->media->contains(fn ($media) => $media->id === $file->media_id || rawurldecode(basename($media->file_path)) === $file->filename);
        });
    }

    private function hasReferences(CampaignDeviceFile $file): bool
    {
        if ($this->references($file)->exists()) {
            return true;
        }
        // Protect active legacy campaigns even if their delivery snapshot cannot yet be reconciled.
        foreach (Campaign::where(fn ($q) => $q->where('status', 'active')->orWhere(fn ($q) => $q->where('status', 'scheduled')->where(fn ($q) => $q->whereNull('start_date')->orWhere('start_date', '<=', now()))))->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>', now()))->with('media')->get() as $campaign) {
            $keys = $campaign->target_devices;
            if ($keys === null) {
                $keys = Device::whereIn('id', $campaign->deviceCampaigns()->pluck('device_id'))->get()->map(fn ($d) => CampaignTargetCatalog::z2Key($d->mac_address))->all();
            }
            if (! in_array($file->target_key, $keys, true)) {
                continue;
            }
            foreach ($campaign->media as $media) {
                if ($media->id === $file->media_id || rawurldecode(basename($media->file_path)) === $file->filename) {
                    return true;
                }
            }
        }

        return false;
    }

    public function processDevice(string $target): void
    {
        $lock = Cache::lock(self::lockName($target), 2000);
        if (! $lock->get()) {
            return;
        }
        $failedFile = null;
        $winner = null;
        try {
            $files = CampaignDeviceFile::where('target_key', $target)->orderBy('id')->get();
            $winner = Campaign::where('status', 'active')->where(fn ($q) => $q->whereNull('start_date')->orWhere('start_date', '<=', now()))->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>', now()))
                ->whereHas('deliveries', fn ($q) => $q->whereNull('released_at')->whereHas('file', fn ($f) => $f->where('target_key', $target)))
                ->orderByDesc('priority')->orderBy('id')->first();
            $playback = app(CampaignPlaybackRestoration::class)->begin($target, $files, $winner !== null);
            $baselineTransition = app(CampaignBaselineTransition::class);
            if ($winner && $playback?->baseline !== null && ! $baselineTransition->prepare($playback)) {
                return;
            }
            $currentValid = $winner && $playback?->campaign_id === $winner->id && $winner->deliveries()->whereKey($playback->delivery_id)->whereNull('released_at')->exists();
            if (! $currentValid && $playback?->campaign_id !== null) {
                $playback->update(['campaign_id' => null, 'delivery_id' => null, 'due_at' => null, 'last_error' => null]);
            }
            // A campaign can finish while provisioning has temporarily powered the fan off.
            // Recover before cleanup can return early, and retain the flag if reconnecting fails.
            if (! $winner && $playback?->powered_off) {
                $this->transport->power($target, true);
                $playback->update(['powered_off' => false, 'last_error' => null]);
            }
            // Finalize in-flight physical operations first; never overwrite the 42 desired file.
            foreach ($files->whereIn('state', ['installing', 'removing']) as $file) {
                $failedFile = $file;
                if ($file->state === 'installing' && ! $this->hasReferences($file) && str_starts_with($target, 'z2:')) {
                    $this->transport->remove($file);
                } elseif ($file->state === 'installing') {
                    $this->transport->confirmInstallation($file);
                } else {
                    $this->transport->remove($file);
                }
                $this->clearPlaybackErrorIfVerified($file);
                if ($file->fresh()->state === 'installing' || $file->fresh()->state === 'removing') {
                    return;
                }
                $failedFile = null;
            }
            foreach ($files as $file) {
                $file->refresh();
                if ($this->hasReferences($file)) {
                    continue;
                }
                if ($file->state === 'pending') {
                    $file->update(['state' => 'removed', 'last_error' => null]);

                    continue;
                }
                if (in_array($file->state, ['ready'])) {
                    $failedFile = $file;
                    $this->transport->remove($file);

                    return;
                }
            }
            if ($winner && $playback?->baseline !== null && ! $baselineTransition->removePrevious($playback, function () use ($winner): bool {
                $winner->refresh();

                return $winner->status === 'active' && (! $winner->end_date || $winner->end_date->isFuture());
            })) {
                return;
            }
            // Install all live campaign deliveries, even when a higher priority campaign owns playback.
            foreach ($files as $file) {
                $file->refresh();
                if ($file->state === 'pending' && $this->references($file)->whereHas('campaign', fn ($q) => $q->where('status', 'active')->where(fn ($q) => $q->whereNull('start_date')->orWhere('start_date', '<=', now())))->exists()) {
                    $failedFile = $file;
                    $this->publishFile($file);
                    $this->clearPlaybackErrorIfVerified($file);

                    return;
                }
            }
            if (! $winner) {
                if ($playback?->baseline !== null && app(CampaignBaselineBackup::class)->files($playback)->contains(fn ($file) => in_array($file->state, ['removing', 'removed', 'restoring', 'restored', 'needs_review'], true))) {
                    if (! $baselineTransition->restorePrevious($playback, fn () => ! Campaign::where('status', 'active')->where(fn ($q) => $q->whereNull('start_date')->orWhere('start_date', '<=', now()))->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>', now()))->whereHas('deliveries', fn ($q) => $q->whereNull('released_at')->whereHas('file', fn ($f) => $f->where('target_key', $target)))->exists())) {
                        return;
                    }
                }
                app(CampaignPlaybackRestoration::class)->restore($playback);

                return;
            }
            $deliveries = $winner->deliveries()->whereNull('released_at')->whereHas('file', fn ($q) => $q->where('target_key', $target))->with('file')->orderBy('position')->get();
            foreach ($deliveries as $delivery) {
                if ($delivery->file->state === 'pending') {
                    $failedFile = $delivery->file;
                    $this->publishFile($delivery->file);
                    $this->clearPlaybackErrorIfVerified($delivery->file);

                    return;
                }
                if ($delivery->file->state !== 'ready') {
                    return;
                }
            }
            $this->rotate($winner, $target, $deliveries);
        } catch (Throwable $e) {
            $message = CampaignError::publicMessage($e);
            if ($failedFile !== null) {
                $failedFile->update(['last_error' => $message]);
            }
            CampaignDevicePlayback::updateOrCreate(['target_key' => $target], ['last_error' => $message]);
            $key = 'campaigns:diagnostic:'.hash('sha256', $target.'|'.$e::class.'|'.$e->getMessage());
            if (Cache::add($key, true, 60)) {
                Log::channel('diagnostics')->error('Falló el procesamiento de campaña para un dispositivo.', [
                    'target' => $target, 'campaign_id' => $winner?->id, 'exception_type' => $e::class, 'reason' => $message,
                ]);
            }
        } finally {
            $lock->release();
        }
    }

    private function clearPlaybackErrorIfVerified(CampaignDeviceFile $file): void
    {
        $file->refresh();
        if ($file->state === 'ready' && $file->confirmed_at !== null) {
            CampaignDevicePlayback::where('target_key', $file->target_key)->update(['last_error' => null]);
        }
    }

    private function publishFile(CampaignDeviceFile $file): void
    {
        $playback = CampaignDevicePlayback::where('target_key', $file->target_key)->first();
        if ($playback?->campaign_id !== null) {
            // The 42 installation protocol also selects its downloaded file. Suspend managed
            // playback while provisioning so a lower-priority upload cannot replace its winner.
            $this->transport->power($file->target_key, false);
            $playback->update(['campaign_id' => null, 'delivery_id' => null, 'due_at' => null, 'powered_off' => true]);
        }
        $this->transport->install($file, fn () => $this->wantedNow($file));
    }

    private function rotate(Campaign $campaign, string $target, $deliveries): void
    {
        if ($deliveries->isEmpty()) {
            return;
        }
        $playback = CampaignDevicePlayback::firstOrCreate(['target_key' => $target]);
        if ($playback->campaign_id === $campaign->id && $playback->due_at?->isFuture()) {
            return;
        }
        $current = $deliveries->search(fn ($d) => $d->id === $playback->delivery_id);
        $next = $playback->campaign_id === $campaign->id && $current !== false ? ($current + 1) % $deliveries->count() : 0;
        $delivery = $deliveries[$next];
        // A finish/pause can arrive after a long upload; recheck immediately before playback.
        $campaign->refresh();
        if ($campaign->status !== 'active' || $campaign->end_date?->lte(now())) {
            return;
        }
        if ($playback->powered_off) {
            $this->transport->power($target, true);
        }
        $this->transport->select($delivery->file);
        $playback->update(['campaign_id' => $campaign->id, 'delivery_id' => $delivery->id, 'due_at' => now()->addSeconds($delivery->duration), 'powered_off' => false, 'last_error' => null]);
    }
}
