@extends('layouts.app')

@section('title', 'Reproducción Instantánea')

@section('content')
@php
    $z2Count = $devicesWithPlaying->where('type', 'z2')->count();
    $wl35Count = $devicesWithPlaying->where('type', 'wl35')->count();
@endphp
<div class="max-w-7xl mx-auto" x-data="instantPlay()">
    {{-- Page Header --}}
    <div class="mb-8">
        <div class="flex items-center gap-2 text-sm text-text-light mb-2">
            <a href="{{ route('dashboard.index') }}" class="hover:text-primary transition-colors">Dashboard</a>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
            <span class="text-text">Reproducción Instantánea</span>
        </div>
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-text">Reproducción Instantánea</h1>
                <p class="text-sm text-text-light mt-1">Reproduce contenido en ventiladores Z2 y WL35 desde una sola selección</p>
            </div>
            <div class="flex items-center gap-2">
                {{-- Mode toggle --}}
                <div class="inline-flex rounded-lg border border-border bg-white p-0.5">
                    <button
                        @click="mode = 'single'"
                        :class="mode === 'single' ? 'bg-primary text-white shadow-sm' : 'text-text-light hover:text-text'"
                        class="px-3 py-1.5 rounded-md text-sm font-medium transition-all"
                    >
                        Individual
                    </button>
                    <button
                        @click="mode = 'bulk'"
                        :class="mode === 'bulk' ? 'bg-primary text-white shadow-sm' : 'text-text-light hover:text-text'"
                        class="px-3 py-1.5 rounded-md text-sm font-medium transition-all"
                    >
                        Masivo
                    </button>
                    <button
                        @click="mode = 'campaign'"
                        :class="mode === 'campaign' ? 'bg-primary text-white shadow-sm' : 'text-text-light hover:text-text'"
                        class="px-3 py-1.5 rounded-md text-sm font-medium transition-all"
                    >
                        Campaña
                    </button>
                </div>
            </div>
        </div>
    </div>

    @if($gatewayError)
        <div class="mb-6 flex items-start gap-3 rounded-xl border border-warning/30 bg-warning/10 px-4 py-3 text-sm text-amber-900">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-warning" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <div class="min-w-0"><div class="font-semibold">Los Z2 locales siguen disponibles, pero no fue posible cargar los WL35.</div><div class="mt-1 break-words text-xs leading-5 text-amber-800">{{ $gatewayError }}</div></div>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Left: Devices panel --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Devices with current playing --}}
            <div class="bg-white rounded-xl border border-border shadow-[0_1px_3px_rgba(0,0,0,0.04)] overflow-hidden">
                <div class="px-6 py-4 border-b border-border flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-text flex items-center gap-2">
                        <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                        Dispositivos
                    </h2>
                    <div class="flex items-center gap-2 text-[11px] font-semibold"><span class="rounded-md bg-amber-100 px-2 py-1 text-amber-700">Z2 {{ $z2Count }}</span><span class="rounded-md bg-sky-100 px-2 py-1 text-sky-700">WL35 {{ $wl35Count }}</span></div>
                </div>

                @if(count($devicesWithPlaying) > 0)
                    <div class="divide-y divide-border">
                        @foreach($devicesWithPlaying as $item)
                            @php($key = (string) $item['key'])
                            <div class="px-6 py-4 transition-colors {{ $item['online'] ? 'hover:bg-surface/50' : 'bg-surface/40 opacity-70' }}"
                                 :class="{
                                    'ring-2 ring-primary/20 bg-primary/[0.02]': (mode === 'single' || mode === 'campaign') && selectedDevice === {{ Js::from($key) }},
                                    'bg-primary/[0.02]': mode === 'bulk' && selectedDevices.includes({{ Js::from($key) }})
                                 }">
                                <div class="flex items-center gap-4">
                                    {{-- Checkbox for bulk mode --}}
                                    <template x-if="mode === 'bulk'">
                                        <label class="flex items-center cursor-pointer">
                                            <input type="checkbox"
                                                   value="{{ $key }}"
                                                   x-model="selectedDevices"
                                                   @disabled(!$item['online'])
                                                   class="w-4 h-4 rounded border-border text-primary focus:ring-primary">
                                        </label>
                                    </template>

                                    {{-- Device info --}}
                                    <button type="button" class="flex-1 min-w-0 text-left {{ $item['online'] ? 'cursor-pointer' : 'cursor-not-allowed' }}"
                                         @if($item['online']) @click="if (mode === 'single' || mode === 'campaign') selectedDevice = {{ Js::from($key) }}" @endif>
                                        <div class="flex items-center gap-2 mb-1">
                                            <h3 class="font-semibold text-text text-sm truncate">{{ $item['name'] }}</h3>
                                            <span class="rounded-md px-1.5 py-0.5 text-[10px] font-bold tracking-wide {{ $item['type'] === 'wl35' ? 'bg-sky-100 text-sky-700' : 'bg-amber-100 text-amber-700' }}">{{ strtoupper($item['type']) }}</span>
                                            <span class="inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 rounded-full
                                                {{ $item['online'] ? 'bg-success/10 text-success' : 'bg-text-muted/10 text-text-muted' }}">
                                                <span class="w-1.5 h-1.5 rounded-full {{ $item['online'] ? 'bg-success' : 'bg-text-muted' }}"></span>
                                                {{ $item['status_label'] }}
                                            </span>
                                        </div>
                                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-text-muted">
                                            <span class="font-mono">{{ $item['identifier'] }}</span>
                                            @if($item['group'])
                                                <span class="flex items-center gap-1">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                    </svg>
                                                    {{ $item['group'] }}
                                                </span>
                                            @endif
                                            @if($item['location'])
                                                <span class="flex items-center gap-1">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                                    </svg>
                                                    {{ $item['location'] }}
                                                </span>
                                            @endif
                                            @if($item['type'] === 'wl35')<span>{{ $item['video_count'] }} videos</span>@endif
                                        </div>
                                    </button>

                                    {{-- Now playing indicator --}}
                                    <div class="text-right flex-shrink-0">
                                        @if($item['current_playing'])
                                            <div class="flex items-center gap-2">
                                                <div class="relative">
                                                    <span class="flex h-2.5 w-2.5">
                                                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary/60 opacity-75"></span>
                                                        <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-primary"></span>
                                                    </span>
                                                </div>
                                                <div>
                                                    <p class="text-xs font-medium text-primary">Reproduciendo</p>
                                                    <p class="text-[11px] text-text-muted max-w-[160px] truncate" title="{{ $item['current_playing'] }}">{{ $item['current_playing'] }}</p>
                                                </div>
                                            </div>
                                        @else
                                            <p class="text-xs text-text-muted">Sin reproducción</p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="px-6 py-12 text-center">
                        <div class="w-12 h-12 rounded-xl bg-surface flex items-center justify-center mx-auto mb-3">
                            <svg class="w-6 h-6 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <p class="text-sm text-text-light">No hay dispositivos disponibles</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Right: Action panel --}}
        <div class="lg:col-span-1 space-y-6">
            {{-- Single play / Campaign play --}}
            <template x-if="mode === 'single' || mode === 'campaign'">
                <div class="bg-white rounded-xl border border-border shadow-[0_1px_3px_rgba(0,0,0,0.04)] sticky top-6">
                    {{-- Panel header --}}
                    <div class="px-6 py-4 border-b border-border">
                        <h2 class="text-lg font-semibold text-text flex items-center gap-2">
                            <template x-if="mode === 'single'">
                                <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </template>
                            <template x-if="mode === 'campaign'">
                                <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/>
                                </svg>
                            </template>
                            <span x-text="mode === 'single' ? 'Reproducir Video' : 'Reproducir Campaña'"></span>
                        </h2>
                    </div>

                    <div class="p-6 space-y-5">
                        {{-- Selected device indicator --}}
                        <div>
                            <label class="block text-xs font-medium text-text-muted uppercase tracking-wider mb-1.5">Dispositivo seleccionado</label>
                            <template x-if="selectedDevice">
                                <div class="flex items-center gap-2 p-3 rounded-lg bg-primary/5 border border-primary/20">
                                    <svg class="w-4 h-4 text-primary flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    <span class="text-sm font-medium text-text" x-text="getDeviceName(selectedDevice)"></span>
                                </div>
                            </template>
                            <template x-if="!selectedDevice">
                                <p class="text-sm text-text-muted italic p-3 rounded-lg bg-surface border border-border">
                                    Haga clic en un dispositivo de la lista
                                </p>
                            </template>
                        </div>

                        {{-- Single mode: select media --}}
                        <template x-if="mode === 'single'">
                            <form action="{{ route('fleet.command') }}" method="POST" class="space-y-4" @submit="submitting = true">
                                @csrf
                                <input type="hidden" name="command" value="play">
                                <input type="hidden" name="targets[]" :value="selectedDevice">
                                <div x-show="selectionHasZ2()">
                                    <label for="media_id_single" class="block text-xs font-medium text-text-muted uppercase tracking-wider mb-1.5">Video</label>
                                    <select name="z2_filename" id="media_id_single" :required="selectionHasZ2()"
                                            class="w-full rounded-lg border border-border bg-surface px-4 py-2.5 text-sm text-text focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-colors">
                                        <option value="">-- Seleccione un video --</option>
                                        @foreach($allMedia as $m)
                                            <option value="{{ $m->file_path }}">{{ $m->name }} {{ $m->duration ? '(' . gmdate('i:s', $m->duration) . ')' : '' }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div x-show="selectionHasWl35()" x-cloak>
                                    <label for="wl35_video_index_single" class="block text-xs font-medium text-text-muted uppercase tracking-wider mb-1.5">Índice de video WL35</label>
                                    <input type="number" name="wl35_video_index" id="wl35_video_index_single" min="1" max="255" value="1" :required="selectionHasWl35()" class="w-full rounded-lg border border-border bg-surface px-4 py-2.5 text-sm text-text outline-none transition-colors focus:border-primary focus:ring-1 focus:ring-primary">
                                    <p class="mt-1.5 text-xs leading-5 text-text-muted">El WL35 identifica sus videos por posición, no por nombre de archivo.</p>
                                </div>
                                <button type="submit"
                                        :disabled="!selectedDevice || submitting"
                                        :class="selectedDevice && !submitting ? 'bg-primary hover:bg-primary/90 cursor-pointer' : 'bg-text-muted/30 cursor-not-allowed'"
                                        class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-lg text-white text-sm font-semibold transition-colors shadow-sm">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                                    </svg>
                                    <span x-text="submitting ? 'Enviando orden…' : 'Reproducir ahora'"></span>
                                </button>
                            </form>
                        </template>

                        {{-- Campaign mode: select campaign --}}
                        <template x-if="mode === 'campaign'">
                            <form action="{{ route('fleet.command') }}" method="POST" class="space-y-4" @submit="submitting = true">
                                @csrf
                                <input type="hidden" name="command" value="play">
                                <input type="hidden" name="targets[]" :value="selectedDevice">
                                <div x-show="selectionHasZ2()">
                                    <label for="campaign_id" class="block text-xs font-medium text-text-muted uppercase tracking-wider mb-1.5">Campaña</label>
                                    <select name="z2_filename" id="campaign_id" :required="selectionHasZ2()"
                                            class="w-full rounded-lg border border-border bg-surface px-4 py-2.5 text-sm text-text focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-colors">
                                        <option value="">-- Seleccione una campaña --</option>
                                        @foreach($campaigns as $c)
                                            @php($campaignMedia = $c->media->first())
                                            <option value="{{ $campaignMedia?->file_path }}" @disabled(!$campaignMedia)>
                                                {{ $c->name }}
                                                ({{ $c->media->count() }} {{ $c->media->count() === 1 ? 'video' : 'videos' }})
                                            </option>
                                        @endforeach
                                    </select>
                                    <p class="mt-1.5 text-xs leading-5 text-text-muted">Se reproduce el primer video asociado a la campaña.</p>
                                </div>
                                <div x-show="selectionHasWl35()" x-cloak>
                                    <label for="wl35_video_index_campaign" class="block text-xs font-medium text-text-muted uppercase tracking-wider mb-1.5">Índice de campaña en WL35</label>
                                    <input type="number" name="wl35_video_index" id="wl35_video_index_campaign" min="1" max="255" value="1" :required="selectionHasWl35()" class="w-full rounded-lg border border-border bg-surface px-4 py-2.5 text-sm text-text outline-none transition-colors focus:border-primary focus:ring-1 focus:ring-primary">
                                    <p class="mt-1.5 text-xs leading-5 text-text-muted">Indica la posición del video ya cargado en este ventilador.</p>
                                </div>
                                <button type="submit"
                                        :disabled="!selectedDevice || submitting"
                                        :class="selectedDevice && !submitting ? 'bg-primary hover:bg-primary/90 cursor-pointer' : 'bg-text-muted/30 cursor-not-allowed'"
                                        class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-lg text-white text-sm font-semibold transition-colors shadow-sm">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/>
                                    </svg>
                                    <span x-text="submitting ? 'Enviando orden…' : 'Publicar campaña'"></span>
                                </button>
                            </form>
                        </template>
                    </div>
                </div>
            </template>

            {{-- Bulk mode panel --}}
            <template x-if="mode === 'bulk'">
                <div class="bg-white rounded-xl border border-border shadow-[0_1px_3px_rgba(0,0,0,0.04)] sticky top-6">
                    <div class="px-6 py-4 border-b border-border">
                        <h2 class="text-lg font-semibold text-text flex items-center gap-2">
                            <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                            </svg>
                            Envío Masivo
                        </h2>
                    </div>

                    <div class="p-6 space-y-5">
                        {{-- Selected count --}}
                        <div>
                            <label class="block text-xs font-medium text-text-muted uppercase tracking-wider mb-1.5">Dispositivos seleccionados</label>
                            <div class="flex items-center gap-2 p-3 rounded-lg bg-surface border border-border">
                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-primary/10 text-primary text-xs font-bold" x-text="selectedDevices.length"></span>
                                <span class="text-sm text-text" x-text="selectedDevices.length === 1 ? 'dispositivo' : 'dispositivos'"></span>
                            </div>
                        </div>

                        {{-- Select all / none --}}
                        <div class="flex gap-2">
                            <button type="button" @click="selectAllDevices()" class="flex-1 px-3 py-1.5 rounded-lg border border-border text-xs font-medium text-text-light hover:bg-surface transition-colors">
                                Seleccionar todos
                            </button>
                            <button type="button" @click="selectedDevices = []" class="flex-1 px-3 py-1.5 rounded-lg border border-border text-xs font-medium text-text-light hover:bg-surface transition-colors">
                                Deseleccionar
                            </button>
                        </div>

                        <form action="{{ route('fleet.command') }}" method="POST" class="space-y-4" @submit="submitting = true">
                            @csrf
                            <input type="hidden" name="command" value="play">
                            <template x-for="deviceKey in selectedDevices" :key="deviceKey">
                                <input type="hidden" name="targets[]" :value="deviceKey">
                            </template>
                            <div x-show="selectionHasZ2()">
                                <label for="media_id_bulk" class="block text-xs font-medium text-text-muted uppercase tracking-wider mb-1.5">Video para Z2</label>
                                <select name="z2_filename" id="media_id_bulk" :required="selectionHasZ2()"
                                        class="w-full rounded-lg border border-border bg-surface px-4 py-2.5 text-sm text-text focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-colors">
                                    <option value="">-- Seleccione un video --</option>
                                    @foreach($allMedia as $m)
                                        <option value="{{ $m->file_path }}">{{ $m->name }} {{ $m->duration ? '(' . gmdate('i:s', $m->duration) . ')' : '' }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div x-show="selectionHasWl35()" x-cloak>
                                <label for="wl35_video_index_bulk" class="block text-xs font-medium text-text-muted uppercase tracking-wider mb-1.5">Índice para WL35</label>
                                <input type="number" name="wl35_video_index" id="wl35_video_index_bulk" min="1" max="255" value="1" :required="selectionHasWl35()" class="w-full rounded-lg border border-border bg-surface px-4 py-2.5 text-sm text-text outline-none transition-colors focus:border-primary focus:ring-1 focus:ring-primary">
                                <p class="mt-1.5 text-xs leading-5 text-text-muted">Todos los WL35 seleccionados reproducirán el mismo índice.</p>
                            </div>
                            <button type="submit"
                                    :disabled="selectedDevices.length === 0 || submitting"
                                    :class="selectedDevices.length > 0 && !submitting ? 'bg-primary hover:bg-primary/90 cursor-pointer' : 'bg-text-muted/30 cursor-not-allowed'"
                                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-lg text-white text-sm font-semibold transition-colors shadow-sm">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                                <span x-text="submitting ? 'Enviando orden…' : 'Enviar a ' + selectedDevices.length + ' dispositivo(s)'"></span>
                            </button>
                        </form>
                    </div>
                </div>
            </template>

            {{-- Quick-play media thumbnails --}}
            <div class="bg-white rounded-xl border border-border shadow-[0_1px_3px_rgba(0,0,0,0.04)]">
                <div class="px-6 py-4 border-b border-border">
                    <h2 class="text-sm font-semibold text-text flex items-center gap-2">
                        <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4v16M17 4v16M3 8h4m10 0h4M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z"/>
                        </svg>
                        Medios disponibles ({{ count($allMedia) }})
                    </h2>
                </div>
                <div class="p-4 space-y-2 max-h-[360px] overflow-y-auto">
                    @forelse($allMedia as $m)
                        <div class="flex items-center gap-3 p-2.5 rounded-lg hover:bg-surface transition-colors group">
                            @if($m->thumbnail)
                                <img src="{{ $m->thumbnail }}"
                                     alt="{{ $m->name }}"
                                     class="w-10 h-10 rounded-lg object-cover bg-surface flex-shrink-0"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div class="w-10 h-10 rounded-lg bg-primary/10 items-center justify-center flex-shrink-0 hidden">
                                    <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                                    </svg>
                                </div>
                            @else
                                <div class="w-10 h-10 rounded-lg bg-primary/10 flex items-center justify-center flex-shrink-0">
                                    <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                                    </svg>
                                </div>
                            @endif
                            <div class="min-w-0 flex-1">
                                <p class="text-xs font-medium text-text truncate">{{ $m->name }}</p>
                                @if($m->duration)
                                    <p class="text-[11px] text-text-muted">{{ gmdate('i:s', $m->duration) }}</p>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-text-muted text-center py-4">No hay medios sincronizados</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function instantPlay() {
        return {
            mode: 'single',
            selectedDevice: null,
            selectedDevices: [],
            submitting: false,

            devices: {{ Js::from($devicesWithPlaying->map(fn (array $item): array => [
                'key' => $item['key'],
                'name' => $item['name'],
                'type' => $item['type'],
                'online' => $item['online'],
            ])->values()) }},

            getDeviceName(key) {
                const dev = this.devices.find(device => device.key === key);
                return dev ? dev.name : 'Desconocido';
            },

            activeTargets() {
                return this.mode === 'bulk'
                    ? this.selectedDevices
                    : (this.selectedDevice ? [this.selectedDevice] : []);
            },

            selectionHasZ2() {
                return this.activeTargets().some(key => key.startsWith('z2:'));
            },

            selectionHasWl35() {
                return this.activeTargets().some(key => key.startsWith('wl35:'));
            },

            selectAllDevices() {
                this.selectedDevices = this.devices.filter(device => device.online).map(device => device.key);
            }
        }
    }
</script>
@endpush
@endsection
