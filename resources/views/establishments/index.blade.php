@extends('layouts.app')

@section('title', 'Establecimientos')

@section('content')
<div x-data="{ search: '' }" class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-text">Establecimientos</h1>
            <p class="mt-1 text-sm text-text-light">Centraliza ubicación, contacto y red Wi‑Fi para Z2 y WL35.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('business-types.index') }}" class="rounded-lg border border-border bg-white px-4 py-2.5 text-sm font-medium text-text transition hover:bg-surface">Tipos de negocio</a>
            <a href="{{ route('establishments.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-primary/20 transition hover:bg-primary/90">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Nuevo establecimiento
            </a>
        </div>
    </div>

    <div class="rounded-xl border border-border bg-white p-4 shadow-[0_1px_3px_rgba(0,0,0,0.04)]">
        <label class="relative block">
            <span class="sr-only">Buscar establecimientos</span>
            <svg class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="search" x-model="search" placeholder="Buscar por nombre, tipo, dirección o contacto…" class="w-full rounded-lg border border-border py-2.5 pl-10 pr-4 text-base outline-none transition placeholder:text-text-muted focus:border-primary focus:ring-2 focus:ring-primary/20 sm:text-sm">
        </label>
    </div>

    <section class="overflow-hidden rounded-xl border border-border bg-white shadow-[0_1px_3px_rgba(0,0,0,0.04)]">
        @if($establishments->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="w-full min-w-[850px] text-left text-sm">
                    <thead><tr class="border-b border-border bg-surface"><th class="px-5 py-4 font-semibold text-text">Establecimiento</th><th class="px-5 py-4 font-semibold text-text">Dirección</th><th class="px-5 py-4 font-semibold text-text">Contacto</th><th class="px-5 py-4 font-semibold text-text">Wi‑Fi</th><th class="px-5 py-4 font-semibold text-text">Dispositivos</th><th class="px-5 py-4 text-right font-semibold text-text">Acciones</th></tr></thead>
                    <tbody class="divide-y divide-border">
                        @foreach($establishments as $establishment)
                            @php($searchable = mb_strtolower($establishment->name.' '.$establishment->businessType?->name.' '.$establishment->address.' '.$establishment->contact_name))
                            <tr x-show="!search || {{ Js::from($searchable) }}.includes(search.toLowerCase())" class="transition hover:bg-surface/50">
                                <td class="px-5 py-4"><div class="max-w-56 break-words font-semibold text-text">{{ $establishment->name }}</div><div class="mt-1 text-xs text-text-muted">{{ $establishment->businessType?->name ?? 'Sin tipo' }}</div></td>
                                <td class="px-5 py-4"><div class="max-w-72 break-words text-text-light">{{ $establishment->address }}</div>@if($establishment->latitude !== null && $establishment->longitude !== null)<a href="https://www.google.com/maps?q={{ $establishment->latitude }},{{ $establishment->longitude }}" target="_blank" rel="noopener noreferrer" class="mt-1 inline-flex text-xs font-medium text-primary hover:underline">Abrir en el mapa</a>@endif</td>
                                <td class="px-5 py-4"><div class="text-text-light">{{ $establishment->contact_name ?: 'Sin contacto' }}</div><div class="mt-1 text-xs text-text-muted">{{ $establishment->contact_phone ?: 'Sin teléfono' }}</div></td>
                                <td class="px-5 py-4"><span class="font-mono text-xs text-text-light">{{ $establishment->wifi_ssid ?: 'Sin configurar' }}</span><div class="mt-1 text-xs text-text-muted">Contraseña protegida</div></td>
                                <td class="px-5 py-4"><span class="font-semibold tabular-nums text-text">{{ $establishment->devices_count + $establishment->wl35_device_profiles_count }}</span><div class="mt-1 text-xs text-text-muted">{{ $establishment->devices_count }} Z2 · {{ $establishment->wl35_device_profiles_count }} WL35</div></td>
                                <td class="px-5 py-4"><div class="flex justify-end gap-1"><a href="{{ route('establishments.edit', $establishment) }}" class="rounded-lg p-2 text-text-light transition hover:bg-primary/10 hover:text-primary" aria-label="Editar {{ $establishment->name }}"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></a><form action="{{ route('establishments.destroy', $establishment) }}" method="POST" onsubmit="return confirm('¿Eliminar este establecimiento?')">@csrf @method('DELETE')<button class="rounded-lg p-2 text-text-light transition hover:bg-danger/10 hover:text-danger disabled:cursor-not-allowed disabled:opacity-40" @disabled(($establishment->devices_count + $establishment->wl35_device_profiles_count) > 0) aria-label="Eliminar {{ $establishment->name }}"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button></form></div></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($establishments->hasPages())<div class="border-t border-border px-5 py-4">{{ $establishments->links() }}</div>@endif
        @else
            <div class="px-6 py-14 text-center"><p class="font-semibold text-text">Todavía no hay establecimientos</p><p class="mt-1 text-sm text-text-light">Crea el primero para poder asociar dispositivos.</p><a href="{{ route('establishments.create') }}" class="mt-5 inline-flex rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white">Crear establecimiento</a></div>
        @endif
    </section>
</div>
@endsection
