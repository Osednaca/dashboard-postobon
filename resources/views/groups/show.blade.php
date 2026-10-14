@extends('layouts.app')

@section('title', $group->name)

@section('content')
@php
    $deviceCount = $group->devices->count() + $group->wl35Devices->count();
    $canViewDevices = auth()->user()->viewsDevices();
    $latitude = filter_var($group->latitude, FILTER_VALIDATE_FLOAT);
    $longitude = filter_var($group->longitude, FILTER_VALIDATE_FLOAT);
    $hasCoordinates = $latitude !== false && $longitude !== false && abs($latitude) <= 90 && abs($longitude) <= 180;
    $mapUrl = $hasCoordinates ? 'https://www.google.com/maps?q=' . $latitude . ',' . $longitude : null;
@endphp
<div class="mx-auto max-w-6xl space-y-6" x-data="{ showCampaignModal: false }">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div><a href="{{ route('groups.index') }}" class="text-sm font-medium text-primary hover:underline">← Grupos</a><h1 class="mt-3 text-2xl font-bold text-text">{{ $group->name }}</h1><p class="mt-1 text-sm text-text-light">{{ $group->description }}</p></div>
        @can('update', $group)
            <a href="{{ route('groups.edit', $group) }}" class="rounded-lg border border-border bg-white px-4 py-2.5 text-sm font-medium text-text hover:bg-surface">Editar Grupo</a>
        @endcan
    </div>
    <section class="rounded-xl border border-border bg-white p-6">
        <h2 class="text-lg font-semibold text-text">Detalles del Grupo</h2>
        <dl class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            <div><dt class="text-xs font-medium uppercase text-text-muted">Tipo de negocio</dt><dd class="mt-1 text-sm text-text">{{ $group->businessType?->name ?? 'Sin clasificación' }}</dd></div>
            <div><dt class="text-xs font-medium uppercase text-text-muted">Ciudad</dt><dd class="mt-1 text-sm text-text">{{ $group->city ?: 'Sin ciudad' }}</dd></div>
            <div><dt class="text-xs font-medium uppercase text-text-muted">Dirección</dt><dd class="mt-1 text-sm text-text">{{ $group->address ?: 'Sin dirección' }}</dd></div>
            <div><dt class="text-xs font-medium uppercase text-text-muted">Dispositivos</dt><dd class="mt-1 text-sm text-text">{{ $deviceCount }} dispositivo{{ $deviceCount !== 1 ? 's' : '' }}<span class="mt-1 block text-xs text-text-muted">Modelo 42: {{ $group->devices->count() }} · Modelo 35: {{ $group->wl35Devices->count() }}</span></dd></div>
        </dl>
        <p class="mt-5 text-xs leading-5 text-text-muted">Para segmentar campañas por tipo de negocio se utiliza el establecimiento de cada equipo. La clasificación del grupo no cambia esos datos.</p>
        @if($mapUrl)
            <iframe title="Mapa de {{ $group->name }}" src="{{ $mapUrl . '&output=embed' }}" class="mt-5 h-72 w-full rounded-lg border-0" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
            <a href="{{ $mapUrl }}" target="_blank" rel="noopener noreferrer" class="mt-3 inline-block text-sm font-medium text-primary hover:underline">Abrir ubicación en Google Maps</a>
        @endif
    </section>
    <section class="overflow-hidden rounded-xl border border-border bg-white">
        <div class="border-b border-border p-6"><h2 class="text-lg font-semibold text-text">Dispositivos en este Grupo</h2><p class="mt-1 text-sm text-text-light">Equipos 42 y 35 y su establecimiento asociado.</p></div>
        @if($deviceCount)
            <div class="overflow-x-auto"><table class="w-full text-left text-sm">
                <thead class="border-b border-border bg-surface"><tr><th class="px-5 py-3 font-semibold">Nombre</th><th class="px-5 py-3 font-semibold">Modelo</th><th class="px-5 py-3 font-semibold">Identificador</th><th class="px-5 py-3 font-semibold">Establecimiento</th><th class="px-5 py-3 font-semibold">Tipo de negocio</th>@if($canViewDevices)<th class="px-5 py-3 text-right font-semibold">Detalles</th>@endif</tr></thead>
                <tbody class="divide-y divide-border">
                    @foreach(['z2' => $group->devices, 'wl35' => $group->wl35Devices] as $type => $devices)
                        @foreach($devices as $device)
                            <tr class="hover:bg-surface/50">
                                <td class="px-5 py-4 font-medium text-text">{{ $device->name }}</td>
                                <td class="px-5 py-4">{{ $type === 'wl35' ? '35' : '42' }}</td>
                                <td class="px-5 py-4 font-mono text-xs text-text-light">{{ $type === 'wl35' ? $device->device_id : $device->mac_address }}</td>
                                <td class="px-5 py-4 text-text-light">{{ $device->establishmentProfile?->name ?? $device->establishment ?? 'Sin establecimiento' }}</td>
                                <td class="px-5 py-4 text-text-light">{{ $device->establishmentProfile?->businessType?->name ?? 'Sin tipo de negocio' }}</td>
                                @if($canViewDevices)<td class="px-5 py-4 text-right"><a href="{{ $type === 'wl35' ? route('devices.wl35.show', $device->device_id) : route('devices.show', $device) }}" class="font-medium text-primary hover:underline">Ver dispositivo</a></td>@endif
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table></div>
        @else
            <p class="p-8 text-center text-sm text-text-light">No hay dispositivos en este grupo.</p>
        @endif
    </section>
    @can('update', $group)
        @if($group->devices->isNotEmpty())
            <section class="rounded-xl border border-border bg-white p-6">
                <h2 class="font-semibold text-text">Acciones para equipos 42</h2>
                <p class="mt-1 text-sm text-text-light">Estas acciones se aplican únicamente a los equipos 42 del grupo.</p>
                <div class="mt-4 flex flex-wrap gap-3">
                    <form action="{{ route('groups.power-on', $group) }}" method="POST">@csrf<button type="submit" class="rounded-lg bg-success/10 px-3 py-2 text-sm font-medium text-success">Encender equipos 42</button></form>
                    <form action="{{ route('groups.power-off', $group) }}" method="POST">@csrf<button type="submit" class="rounded-lg bg-warning/10 px-3 py-2 text-sm font-medium text-amber-900">Apagar equipos 42</button></form>
                    @can('viewAny', App\Models\Campaign::class)<button type="button" @click="showCampaignModal = true" class="rounded-lg bg-primary/10 px-3 py-2 text-sm font-medium text-primary">Publicar campaña en equipos 42</button>@endcan
                </div>
            </section>
            @can('viewAny', App\Models\Campaign::class)
                <div x-show="showCampaignModal" x-cloak @keydown.escape.window="showCampaignModal = false" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" role="dialog" aria-modal="true" aria-labelledby="group-campaign-title" @click.self="showCampaignModal = false">
                    <div class="w-full max-w-md rounded-xl border border-border bg-white p-6 shadow-xl">
                        <h2 id="group-campaign-title" class="text-lg font-semibold text-text">Publicar campaña en equipos 42</h2>
                        <p class="mt-2 text-sm text-text-light">Se publicará en los equipos 42 del grupo {{ $group->name }}.</p>
                        <form action="{{ route('groups.publish-campaign', $group) }}" method="POST" class="mt-5 space-y-4">
                            @csrf
                            <div><label for="campaign_id" class="mb-2 block text-sm font-medium">Campaña</label><select id="campaign_id" name="campaign_id" required class="w-full rounded-lg border border-border bg-white px-4 py-2.5 text-sm"><option value="">Seleccionar campaña</option>@foreach(App\Models\Campaign::all() as $campaign)<option value="{{ $campaign->id }}">{{ $campaign->name }}</option>@endforeach</select></div>
                            <div class="flex justify-end gap-3"><button type="button" @click="showCampaignModal = false" class="rounded-lg border border-border px-4 py-2.5 text-sm">Cancelar</button><button type="submit" class="rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-white">Publicar</button></div>
                        </form>
                    </div>
                </div>
            @endcan
        @endif
    @endcan
</div>
@endsection
