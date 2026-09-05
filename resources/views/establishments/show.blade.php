@extends('layouts.app')

@section('title', $establishment->name)

@section('content')
@php
    $totalDevices = $establishment->devices_count + $establishment->wl35_device_profiles_count;
    $hasCoordinates = $establishment->latitude !== null && $establishment->longitude !== null;
@endphp

<div class="mx-auto max-w-7xl space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="min-w-0">
            <a href="{{ route('establishments.index') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>Establecimientos</a>
            <h1 class="mt-3 break-words text-2xl font-bold text-text">{{ $establishment->name }}</h1>
            <p class="mt-1 text-sm text-text-light">{{ $establishment->businessType?->name ?? 'Tipo de negocio sin definir' }} · {{ $totalDevices }} {{ $totalDevices === 1 ? 'dispositivo asociado' : 'dispositivos asociados' }}</p>
        </div>
        <a href="{{ route('establishments.edit', $establishment) }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-primary/20 transition hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-primary/30">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            Editar establecimiento
        </a>
    </div>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(20rem,0.8fr)]">
        <section class="overflow-hidden rounded-xl border border-border bg-white shadow-[0_1px_3px_rgba(0,0,0,0.04)]">
            <div class="border-b border-border px-5 py-4 sm:px-6">
                <h2 class="text-lg font-semibold text-text">Ubicación</h2>
                <p class="mt-1 break-words text-sm leading-6 text-text-light">{{ $establishment->address }}</p>
            </div>

            @if($hasCoordinates)
                <iframe
                    title="Mapa de {{ $establishment->name }}"
                    src="https://www.google.com/maps?q={{ $establishment->latitude }},{{ $establishment->longitude }}&output=embed"
                    class="h-80 w-full border-0"
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade">
                </iframe>
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-border px-5 py-3 text-xs sm:px-6">
                    <span class="font-mono tabular-nums text-text-muted">{{ $establishment->latitude }}, {{ $establishment->longitude }}</span>
                    <a href="https://www.google.com/maps?q={{ $establishment->latitude }},{{ $establishment->longitude }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-primary hover:underline">Abrir en Google Maps</a>
                </div>
            @else
                <div class="px-5 py-10 text-center sm:px-6">
                    <svg class="mx-auto h-9 w-9 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.828 0l-4.243-4.243a8 8 0 1111.314 0zM15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <p class="mt-3 font-semibold text-text">Faltan las coordenadas</p>
                    <p class="mt-1 text-sm text-text-light">Edita el establecimiento y ubica el marcador para mostrar el mapa.</p>
                </div>
            @endif
        </section>

        <aside class="space-y-6">
            <section class="rounded-xl border border-border bg-white p-5 shadow-[0_1px_3px_rgba(0,0,0,0.04)] sm:p-6">
                <h2 class="text-lg font-semibold text-text">Contacto</h2>
                <dl class="mt-4 divide-y divide-border">
                    <div class="grid gap-1 py-3 first:pt-0 sm:grid-cols-[8rem_1fr]"><dt class="text-sm text-text-muted">Responsable</dt><dd class="break-words text-sm font-medium text-text">{{ $establishment->contact_name ?: 'Sin registrar' }}</dd></div>
                    <div class="grid gap-1 py-3 last:pb-0 sm:grid-cols-[8rem_1fr]"><dt class="text-sm text-text-muted">Teléfono</dt><dd class="break-words text-sm font-medium text-text">@if($establishment->contact_phone)<a href="tel:{{ preg_replace('/[^+0-9]/', '', $establishment->contact_phone) }}" class="text-primary hover:underline">{{ $establishment->contact_phone }}</a>@else Sin registrar @endif</dd></div>
                </dl>
            </section>

            <section class="rounded-xl border border-border bg-white p-5 shadow-[0_1px_3px_rgba(0,0,0,0.04)] sm:p-6" x-data="{ revealPassword: false }">
                <div class="flex items-start justify-between gap-3"><div><h2 class="text-lg font-semibold text-text">Red Wi‑Fi</h2><p class="mt-1 text-xs leading-5 text-text-muted">Credenciales cifradas en la base de datos.</p></div><svg class="h-5 w-5 shrink-0 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0-1.1.9-2 2-2s2 .9 2 2M5 8.5a10 10 0 0114 0M8.5 12a5 5 0 017 0M12 17h.01"/></svg></div>
                <dl class="mt-4 divide-y divide-border">
                    <div class="grid gap-1 py-3 first:pt-0 sm:grid-cols-[8rem_1fr]"><dt class="text-sm text-text-muted">ID Wi‑Fi</dt><dd class="break-all font-mono text-sm text-text">{{ $establishment->wifi_ssid ?: 'Sin configurar' }}</dd></div>
                    <div class="grid gap-2 py-3 last:pb-0 sm:grid-cols-[8rem_1fr]"><dt class="text-sm text-text-muted">Password</dt><dd class="min-w-0">@if($establishment->wifi_password)<div class="flex items-center gap-2"><span class="min-w-0 flex-1 break-all font-mono text-sm text-text" x-text="revealPassword ? {{ Js::from($establishment->wifi_password) }} : '••••••••••••'"></span><button type="button" @click="revealPassword = !revealPassword" class="shrink-0 rounded-lg border border-border px-2.5 py-1.5 text-xs font-semibold text-text-light transition hover:bg-surface" x-text="revealPassword ? 'Ocultar' : 'Mostrar'" :aria-label="revealPassword ? 'Ocultar password Wi‑Fi' : 'Mostrar password Wi‑Fi'"></button></div>@else<span class="text-sm text-text">Sin configurar</span>@endif</dd></div>
                </dl>
            </section>
        </aside>
    </div>

    <section class="overflow-hidden rounded-xl border border-border bg-white shadow-[0_1px_3px_rgba(0,0,0,0.04)]">
        <div class="flex flex-col gap-2 border-b border-border px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
            <div><h2 class="text-lg font-semibold text-text">Dispositivos Z2</h2><p class="mt-1 text-sm text-text-light">{{ $establishment->devices_count }} asociados a este establecimiento.</p></div>
            <a href="{{ route('devices.create') }}" class="text-sm font-semibold text-primary hover:underline">Registrar Z2</a>
        </div>
        @forelse($z2Devices as $device)
            <a href="{{ route('devices.show', $device) }}" class="flex flex-col gap-3 border-b border-border px-5 py-4 transition last:border-b-0 hover:bg-surface/60 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <div class="min-w-0"><div class="break-words font-semibold text-text">{{ $device->name }}</div><div class="mt-1 break-all font-mono text-xs text-text-muted">{{ $device->mac_address }}</div></div>
                <div class="flex items-center gap-3"><x-device-status-indicator :status="$device->status" /><span class="inline-flex items-center gap-1 text-xs font-medium text-primary">Ver detalle<svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg></span></div>
            </a>
        @empty
            <div class="px-6 py-10 text-center"><p class="font-semibold text-text">No hay Z2 asociados</p><p class="mt-1 text-sm text-text-light">Puedes registrar uno nuevo o editar un dispositivo existente.</p></div>
        @endforelse
        @if($z2Devices->hasPages())<div class="border-t border-border px-5 py-4 sm:px-6">{{ $z2Devices->links() }}</div>@endif
    </section>

    <section class="overflow-hidden rounded-xl border border-border bg-white shadow-[0_1px_3px_rgba(0,0,0,0.04)]">
        <div class="border-b border-border px-5 py-4 sm:px-6"><h2 class="text-lg font-semibold text-text">Dispositivos WL35</h2><p class="mt-1 text-sm text-text-light">{{ $establishment->wl35_device_profiles_count }} asociados a este establecimiento.</p></div>
        @forelse($wl35Devices as $device)
            <a href="{{ route('devices.wl35.show', ['deviceId' => $device->device_id]) }}" class="flex flex-col gap-3 border-b border-border px-5 py-4 transition last:border-b-0 hover:bg-surface/60 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <div class="min-w-0"><div class="break-words font-semibold text-text">{{ $device->name }}</div><div class="mt-1 break-all font-mono text-xs text-text-muted">{{ $device->device_id }}</div></div>
                <span class="inline-flex items-center gap-1 text-xs font-medium text-primary">Ver detalle<svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg></span>
            </a>
        @empty
            <div class="px-6 py-10 text-center"><p class="font-semibold text-text">No hay WL35 asociados</p><p class="mt-1 text-sm text-text-light">Asigna el establecimiento desde la página de detalle del WL35.</p></div>
        @endforelse
        @if($wl35Devices->hasPages())<div class="border-t border-border px-5 py-4 sm:px-6">{{ $wl35Devices->links() }}</div>@endif
    </section>
</div>
@endsection
