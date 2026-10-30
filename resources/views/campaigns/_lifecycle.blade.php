@php
    $deliveries = $campaign->deliveries()->with(['file.media', 'file.deliveries.campaign'])->orderBy('position')->get();
    $recipientNames = app(\App\Services\CampaignTargetCatalog::class)->all()->pluck('name', 'key');
    $targets = $deliveries->pluck('file.target_key')->filter()->unique()->values();
    $playbackByTarget = \App\Models\CampaignDevicePlayback::whereIn('target_key', $targets)->get()->keyBy('target_key');
    $activity = app(\App\Services\Campaigns\CampaignActivity::class);
    $lastTick = $activity->lastTick();
    $campaignError = \App\Services\Campaigns\CampaignError::safeStored($campaign->lifecycle_error);
    $lifecycleLabels = [
        'pending' => 'Publicación pendiente',
        'installing' => 'Esperando confirmación de publicación',
        'ready' => 'Publicado',
        'removing' => 'Borrado pendiente de confirmación',
        'removed' => 'Borrado confirmado',
        'reported_absent' => 'Ausente del reporte; borrado físico sin verificar',
        'shared' => 'Conservado por otra campaña',
        'preserved' => 'Conservado por seguridad',
        'needs_review' => 'Requiere revisión',
    ];
@endphp

<section aria-labelledby="campaign-lifecycle-title" class="rounded-xl border border-border bg-white p-5 space-y-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h3 id="campaign-lifecycle-title" class="text-lg font-semibold text-text">Publicación y limpieza</h3>
            <p class="mt-1 text-sm text-text-light">Los videos se reproducen en secuencia repetida durante la campaña. Al finalizar, se retiran de la SD salvo que otra campaña los necesite o no se puedan identificar con seguridad.</p>
        </div>
        <a href="{{ route('campaigns.show', $campaign) }}" class="text-sm font-medium text-primary hover:underline">Actualizar estado</a>
    </div>
    <p class="text-sm text-text-muted">Finalizar una campaña no confirma el borrado. Una orden aceptada permanece pendiente hasta recibir evidencia del dispositivo; los equipos sin conexión pueden tardar en completar la operación.</p>
    <p class="text-sm text-text-muted">Si no hay otra campaña que continúe la reproducción, pausar o finalizar envía una orden de apagado al equipo. Reanudar la secuencia vuelve a encenderlo.</p>
    @if($campaign->status === 'active' && $deliveries->contains(fn ($delivery) => $delivery->file?->state === 'pending'))
        <p class="text-sm text-text-muted" role="status">
            @if($lastTick)
                Última actividad observada del programador de campañas: {{ $lastTick }}.
            @else
                No se ha observado actividad reciente del programador de campañas. Comprueba su configuración y vuelve a actualizar el estado.
            @endif
            Esta observación no confirma que se haya publicado el video.
        </p>
    @endif

    @if($campaign->duration_preparation_state === 'pending')
        <p role="status" class="rounded-lg bg-surface p-4 text-sm text-text-light">Preparando la duración de los videos. La campaña se activará cuando estén listos.</p>
    @endif
    @if($campaignError)
        <div role="alert" class="rounded-lg border border-danger/30 bg-danger/5 p-4 text-sm text-danger">
            <p class="font-semibold">No se pudo preparar o actualizar la campaña</p>
            <p class="mt-1 break-words">{{ $campaignError }}</p>
            <p class="mt-1">Revisa los videos y destinatarios antes de continuar. La programación no implica una entrega completada.</p>
        </div>
    @endif

    @if($deliveries->isEmpty())
        <p class="rounded-lg bg-surface p-4 text-sm text-text-light">Aún no hay entregas registradas. El estado de la campaña por sí solo no confirma que los videos estén en los dispositivos.</p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <caption class="sr-only">Estado de cada video y dispositivo de la campaña</caption>
                <thead class="border-b border-border text-text-muted">
                    <tr>
                        <th scope="col" class="py-3 pr-4 font-medium">Dispositivo</th>
                        <th scope="col" class="py-3 pr-4 font-medium">Video</th>
                        <th scope="col" class="py-3 font-medium">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach($deliveries as $delivery)
                        @php
                            $file = $delivery->file;
                            $state = $delivery->cleanup_status;
                            $stateLabel = $state === 'removed' && $file?->confirmed_at === null
                                ? 'Sin archivo pendiente de retirar'
                                : ($lifecycleLabels[$state] ?? 'Estado no disponible');
                            $playback = $playbackByTarget->get($file?->target_key);
                            $fileError = \App\Services\Campaigns\CampaignError::safeStored($file?->last_error);
                            $playbackError = \App\Services\Campaigns\CampaignError::safeStored($playback?->last_error);
                            $lastDeviceStart = $file?->target_key ? $activity->lastDeviceStart($file->target_key) : null;
                        @endphp
                        <tr>
                            <td class="py-3 pr-4 align-top">
                                <span class="font-medium text-text">{{ $recipientNames->get($file?->target_key) ?: 'Dispositivo no disponible' }}</span>
                                <span class="block text-xs text-text-muted break-all">{{ $file?->target_key }}</span>
                            </td>
                            <td class="py-3 pr-4 align-top">
                                <span class="text-text">{{ $file?->media?->name ?: ($file?->filename ?: 'Video no disponible') }}</span>
                                <span class="block text-xs text-text-muted">Orden #{{ $delivery->position }}</span>
                            </td>
                            <td class="py-3 align-top">
                                <span class="font-medium {{ $state === 'needs_review' ? 'text-danger' : ($state === 'removed' ? 'text-success' : 'text-text') }}">{{ $stateLabel }}</span>
                                @if($state === 'shared')
                                    <p class="mt-1 text-xs text-text-muted">Se conserva mientras otra campaña lo necesite.</p>
                                @elseif($state === 'needs_review')
                                    <p class="mt-1 text-xs text-text-muted">No se volverá a borrar por un índice incierto. Revisa el contenido del dispositivo antes de intervenir.</p>
                                @elseif($state === 'reported_absent')
                                    <p class="mt-1 text-xs text-text-muted">El modelo 42 ya no incluye este video en su reporte, pero no permite confirmar que el archivo haya desaparecido de la SD.</p>
                                @elseif($state === 'preserved')
                                    <p class="mt-1 text-xs text-text-muted">No hay una identificación suficiente para retirarlo sin arriesgar otro archivo.</p>
                                @endif
                                @if($fileError)
                                    <p class="mt-1 text-xs text-danger break-words">{{ $fileError }}</p>
                                @endif
                                @if($playbackError && $playbackError !== $fileError)
                                    <p class="mt-1 text-xs text-danger break-words">Error del dispositivo: {{ $playbackError }}</p>
                                @endif
                                @if(in_array($state, ['pending', 'installing'], true))
                                    @if($lastDeviceStart)
                                        <p class="mt-1 text-xs text-text-muted">Último intento observado: {{ $lastDeviceStart }}.</p>
                                    @else
                                        <p class="mt-1 text-xs text-text-muted">Aún no se ha observado un intento reciente para este dispositivo.</p>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
