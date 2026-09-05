@extends('layouts.app')

@section('title', ($profile?->name ?? $device['name'] ?? $device['id']).' · WL35')

@section('content')
@php
    $deviceId = (string) $device['id'];
    $fleetKey = (string) ($device['key'] ?? 'wl35:'.$deviceId);
    $displayName = $profile?->name ?: ($device['name'] ?? $deviceId);
    $online = (bool) (($device['online'] ?? false) && ($device['connected'] ?? false));
    $powered = (bool) ($device['power'] ?? false);
    $bluetooth = (bool) ($device['bluetooth'] ?? false);
    $sessionReady = (bool) ($device['session_ready'] ?? false);
    $uploading = (bool) ($device['uploading'] ?? false);
    $videoCount = max(0, (int) ($device['video_count'] ?? 0));
    $currentVideo = $device['current_video'] ?? null;
    $volume = min(10, max(1, (int) ($device['volume'] ?? 7)));
    $statusLabel = !$online ? 'Fuera de línea' : ($powered ? 'En línea' : 'Apagado');
    $statusClass = !$online ? 'bg-slate-100 text-slate-600' : ($powered ? 'bg-success/10 text-success' : 'bg-danger/10 text-danger');
    $lastSeen = !empty($device['last_seen']) ? \Illuminate\Support\Carbon::parse($device['last_seen'])->diffForHumans() : 'Sin registro';
@endphp

<div
    x-data="{
        showFormatModal: false,
        showDeleteModal: false,
        deleteIndex: null,
        volume: {{ $volume }},
        submitting: false
    }"
    class="space-y-6"
>
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <nav class="mb-2 flex items-center gap-2 text-xs text-text-muted" aria-label="Ruta de navegación">
                <a href="{{ route('devices.index') }}" class="transition hover:text-primary">Dispositivos</a>
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <span aria-current="page">{{ $displayName }}</span>
            </nav>
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-bold text-text">{{ $displayName }}</h1>
                <span class="rounded-md bg-sky-100 px-2 py-1 text-[10px] font-bold uppercase tracking-widest text-sky-700">WL35</span>
                <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">{{ $statusLabel }}</span>
            </div>
            <p class="mt-1 font-mono text-xs text-text-muted">{{ $deviceId }}</p>
        </div>

        <div class="flex flex-wrap gap-2">
            <form action="{{ route('fleet.command') }}" method="POST">
                @csrf
                <input type="hidden" name="command" value="power">
                <input type="hidden" name="targets[]" value="{{ $fleetKey }}">
                <button name="value" value="1" @disabled(!$online || $powered)
                    class="inline-flex items-center gap-2 rounded-lg border border-success/30 bg-success/5 px-3.5 py-2.5 text-sm font-semibold text-success transition hover:bg-success/10 disabled:cursor-not-allowed disabled:opacity-40">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    Encender
                </button>
            </form>
            <form action="{{ route('fleet.command') }}" method="POST">
                @csrf
                <input type="hidden" name="command" value="power">
                <input type="hidden" name="targets[]" value="{{ $fleetKey }}">
                <button name="value" value="0" @disabled(!$online || !$powered)
                    class="inline-flex items-center gap-2 rounded-lg border border-danger/25 bg-danger/5 px-3.5 py-2.5 text-sm font-semibold text-danger transition hover:bg-danger/10 disabled:cursor-not-allowed disabled:opacity-40">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                    Apagar
                </button>
            </form>
            <form action="{{ route('fleet.command') }}" method="POST">
                @csrf
                <input type="hidden" name="command" value="bluetooth">
                <input type="hidden" name="targets[]" value="{{ $fleetKey }}">
                <button name="value" value="{{ $bluetooth ? 0 : 1 }}" @disabled(!$online || !$sessionReady || $uploading)
                    class="inline-flex items-center gap-2 rounded-lg border border-primary/25 px-3.5 py-2.5 text-sm font-semibold transition disabled:cursor-not-allowed disabled:opacity-40 {{ $bluetooth ? 'bg-primary text-white' : 'bg-white text-primary hover:bg-primary/5' }}">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6.5 7.5L12 12l-5.5 4.5V7.5zM17.5 7.5L12 12l5.5 4.5V7.5zM12 12v6"/></svg>
                    Bluetooth {{ $bluetooth ? 'OFF' : 'ON' }}
                </button>
            </form>
        </div>
    </div>

    @if($gatewayError)
        <div class="flex items-start gap-3 rounded-xl border border-warning/30 bg-warning/10 px-4 py-3 text-sm text-amber-900">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-warning" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <div>
                <p class="font-semibold">El estado en vivo no está disponible.</p>
                <p class="mt-1 text-xs leading-5 text-amber-800">La información administrativa se conserva y puede editarse. {{ $gatewayError }}</p>
            </div>
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-xl border border-danger/20 bg-danger/5 px-4 py-3 text-sm text-danger" role="alert">
            <p class="font-semibold">Revisa la información ingresada.</p>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-xs">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.55fr)_minmax(21rem,0.8fr)]">
        <div class="space-y-6">
            <section class="rounded-xl border border-border bg-white p-5 shadow-[0_1px_3px_rgba(0,0,0,0.04)] sm:p-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-text">Estado del dispositivo</h2>
                        <p class="mt-1 text-sm text-text-light">Telemetría reportada por el agente WL35.</p>
                    </div>
                    <a href="{{ route('devices.wl35.show', ['deviceId' => $deviceId]) }}" class="inline-flex items-center gap-2 rounded-lg border border-border px-3 py-2 text-xs font-semibold text-text-light transition hover:bg-surface">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        Actualizar estado
                    </a>
                </div>

                <dl class="mt-5 grid gap-px overflow-hidden rounded-xl border border-border bg-border sm:grid-cols-2 lg:grid-cols-3">
                    <div class="bg-white p-4"><dt class="text-xs font-medium text-text-muted">Dirección IP</dt><dd class="mt-1 font-mono text-sm font-semibold text-text">{{ $device['ip'] ?? '—' }}</dd></div>
                    <div class="bg-white p-4"><dt class="text-xs font-medium text-text-muted">Firmware</dt><dd class="mt-1 truncate font-mono text-xs font-semibold text-text" title="{{ $device['firmware_version'] ?? '' }}">{{ $device['firmware_version'] ?? '—' }}</dd></div>
                    <div class="bg-white p-4"><dt class="text-xs font-medium text-text-muted">Último reporte</dt><dd class="mt-1 text-sm font-semibold text-text">{{ $lastSeen }}</dd></div>
                    <div class="bg-white p-4"><dt class="text-xs font-medium text-text-muted">Energía</dt><dd class="mt-1 text-sm font-semibold {{ $powered ? 'text-success' : 'text-text-light' }}">{{ $powered ? 'Encendido' : 'Apagado' }}</dd></div>
                    <div class="bg-white p-4"><dt class="text-xs font-medium text-text-muted">Bluetooth</dt><dd class="mt-1 text-sm font-semibold {{ $bluetooth ? 'text-primary' : 'text-text-light' }}">{{ $bluetooth ? 'Activo' : 'Inactivo' }}</dd></div>
                    <div class="bg-white p-4"><dt class="text-xs font-medium text-text-muted">Sesión con el fan</dt><dd class="mt-1 text-sm font-semibold {{ $sessionReady ? 'text-success' : 'text-warning' }}">{{ $sessionReady ? 'Lista' : 'No disponible' }}</dd></div>
                    <div class="bg-white p-4"><dt class="text-xs font-medium text-text-muted">Video actual</dt><dd class="mt-1 text-sm font-semibold text-text">{{ $currentVideo !== null ? 'Video '.$currentVideo : '—' }}</dd></div>
                    <div class="bg-white p-4"><dt class="text-xs font-medium text-text-muted">Videos en SD</dt><dd class="mt-1 text-sm font-semibold tabular-nums text-text">{{ $videoCount }}</dd></div>
                    <div class="bg-white p-4"><dt class="text-xs font-medium text-text-muted">Transferencia</dt><dd class="mt-1 text-sm font-semibold {{ $uploading ? 'text-warning' : 'text-text' }}">{{ $uploading ? 'En curso' : 'Disponible' }}</dd></div>
                </dl>
            </section>

            <section class="rounded-xl border border-border bg-white p-5 shadow-[0_1px_3px_rgba(0,0,0,0.04)] sm:p-6">
                <div>
                    <h2 class="text-lg font-semibold text-text">Reproducción y volumen</h2>
                    <p class="mt-1 text-sm text-text-light">Controla el contenido almacenado en este ventilador.</p>
                </div>

                <div class="mt-5 grid gap-5 lg:grid-cols-2">
                    <form action="{{ route('fleet.command') }}" method="POST" class="rounded-xl border border-border bg-surface/60 p-4">
                        @csrf
                        <input type="hidden" name="command" value="play">
                        <input type="hidden" name="targets[]" value="{{ $fleetKey }}">
                        <label for="wl35_video_index" class="block text-sm font-semibold text-text">Reproducir por índice</label>
                        <p class="mt-1 text-xs text-text-muted">Los videos WL35 se identifican por su posición en la tarjeta SD.</p>
                        <div class="mt-4 flex gap-2">
                            <input id="wl35_video_index" name="wl35_video_index" type="number" min="1" max="{{ max(1, $videoCount) }}" value="{{ min(max(1, (int) ($currentVideo ?? 1)), max(1, $videoCount)) }}" required
                                class="min-w-0 flex-1 rounded-lg border border-border bg-white px-3 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20">
                            <button @disabled(!$online || !$sessionReady || $uploading || $videoCount === 0) class="rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-40">Reproducir</button>
                        </div>
                    </form>

                    <form action="{{ route('devices.wl35.volume', ['deviceId' => $deviceId]) }}" method="POST" class="rounded-xl border border-border bg-surface/60 p-4">
                        @csrf
                        <div class="flex items-center justify-between gap-3">
                            <label for="volume" class="text-sm font-semibold text-text">Volumen</label>
                            <output for="volume" class="rounded-md bg-white px-2.5 py-1 font-mono text-sm font-bold text-primary" x-text="volume"></output>
                        </div>
                        <input id="volume" name="volume" type="range" min="1" max="10" step="1" x-model="volume" class="mt-5 w-full accent-primary" @disabled(!$online || !$sessionReady || $uploading)>
                        <div class="mt-1 flex justify-between text-[10px] text-text-muted" aria-hidden="true"><span>1</span><span>5</span><span>10</span></div>
                        <button @disabled(!$online || !$sessionReady || $uploading) class="mt-4 w-full rounded-lg border border-primary/25 bg-white px-4 py-2.5 text-sm font-semibold text-primary transition hover:bg-primary/5 disabled:cursor-not-allowed disabled:opacity-40">Aplicar volumen</button>
                    </form>
                </div>

                <form action="{{ route('fleet.upload') }}" method="POST" enctype="multipart/form-data" class="mt-5 rounded-xl border border-dashed border-border p-4">
                    @csrf
                    <input type="hidden" name="targets[]" value="{{ $fleetKey }}">
                    <label for="wl35_video_upload" class="block text-sm font-semibold text-text">Subir un video a este WL35</label>
                    <p class="mt-1 text-xs text-text-muted">El MP4 se guardará en el VPS y la conversión continuará en segundo plano.</p>
                    <div class="mt-4 flex flex-col gap-3 sm:flex-row">
                        <input id="wl35_video_upload" type="file" name="video" accept="video/mp4,.mp4" required @disabled(!$online || !$sessionReady || $uploading)
                            class="min-w-0 flex-1 rounded-lg border border-border bg-surface text-sm text-text file:mr-3 file:border-0 file:bg-secondary file:px-3 file:py-2.5 file:font-semibold file:text-white disabled:opacity-40">
                        <button @disabled(!$online || !$sessionReady || $uploading) class="rounded-lg bg-secondary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-secondary/90 disabled:cursor-not-allowed disabled:opacity-40">Subir y distribuir</button>
                    </div>
                </form>
            </section>

            <section class="overflow-hidden rounded-xl border border-border bg-white shadow-[0_1px_3px_rgba(0,0,0,0.04)]">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border px-5 py-4 sm:px-6">
                    <div>
                        <h2 class="text-lg font-semibold text-text">Videos en la tarjeta SD</h2>
                        <p class="mt-1 text-sm text-text-light">Reproduce o elimina un archivo individual por su índice.</p>
                    </div>
                    <button type="button" @click="showFormatModal = true" @disabled(!$online || !$sessionReady || $uploading || $videoCount === 0)
                        class="inline-flex items-center gap-2 rounded-lg border border-danger/25 px-3 py-2 text-xs font-semibold text-danger transition hover:bg-danger/5 disabled:cursor-not-allowed disabled:opacity-40">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 7h14M9 11v6m6-6v6M8 7l1-3h6l1 3m1 0-1 13H8L7 7"/></svg>
                        Formatear SD
                    </button>
                </div>

                @if($videoCount > 0)
                    <div class="divide-y divide-border">
                        @for($index = $videoCount; $index >= 1; $index--)
                            <div class="flex items-center justify-between gap-4 px-5 py-3.5 sm:px-6">
                                <div class="flex min-w-0 items-center gap-3">
                                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg {{ (int) $currentVideo === $index ? 'bg-primary text-white' : 'bg-surface text-text-muted' }}">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold text-text">Video {{ $index }}</p>
                                        <p class="text-xs text-text-muted">Índice {{ $index }}{{ (int) $currentVideo === $index ? ' · en reproducción' : '' }}</p>
                                    </div>
                                </div>
                                <div class="flex shrink-0 items-center gap-1">
                                    <form action="{{ route('fleet.command') }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="command" value="play">
                                        <input type="hidden" name="targets[]" value="{{ $fleetKey }}">
                                        <input type="hidden" name="wl35_video_index" value="{{ $index }}">
                                        <button @disabled(!$online || !$sessionReady || $uploading) class="rounded-lg p-2 text-text-light transition hover:bg-primary/10 hover:text-primary disabled:cursor-not-allowed disabled:opacity-40" title="Reproducir video {{ $index }}" aria-label="Reproducir video {{ $index }}">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/></svg>
                                        </button>
                                    </form>
                                    <button type="button" @click="deleteIndex = {{ $index }}; showDeleteModal = true" @disabled(!$online || !$sessionReady || $uploading)
                                        class="rounded-lg p-2 text-text-light transition hover:bg-danger/10 hover:text-danger disabled:cursor-not-allowed disabled:opacity-40" title="Eliminar video {{ $index }}" aria-label="Eliminar video {{ $index }}">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </div>
                        @endfor
                    </div>
                @else
                    <div class="px-6 py-12 text-center">
                        <div class="mx-auto flex h-11 w-11 items-center justify-center rounded-xl bg-surface text-text-muted">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4v16M17 4v16M3 8h4m10 0h4M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z"/></svg>
                        </div>
                        <p class="mt-3 text-sm font-semibold text-text">No hay videos registrados</p>
                        <p class="mt-1 text-xs text-text-muted">Cuando el fan reporte archivos, aparecerán aquí por índice.</p>
                    </div>
                @endif
            </section>
        </div>

        <aside>
            <form action="{{ route('devices.wl35.update', ['deviceId' => $deviceId]) }}" method="POST" class="rounded-xl border border-border bg-white p-5 shadow-[0_1px_3px_rgba(0,0,0,0.04)] xl:sticky xl:top-6 sm:p-6">
                @csrf
                @method('PATCH')
                <div>
                    <h2 class="text-lg font-semibold text-text">Información administrativa</h2>
                    <p class="mt-1 text-sm leading-5 text-text-light">Estos datos pertenecen al dashboard y no dependen de que el WL35 esté conectado.</p>
                </div>

                <div class="mt-5 space-y-4">
                    <div>
                        <label for="name" class="mb-1.5 block text-sm font-medium text-text">Nombre del dispositivo <span class="text-danger">*</span></label>
                        <input id="name" name="name" value="{{ old('name', $profile?->name ?? $displayName) }}" required maxlength="255" class="w-full rounded-lg border border-border px-3.5 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20">
                    </div>
                    <div>
                        <label for="establishment" class="mb-1.5 block text-sm font-medium text-text">Establecimiento</label>
                        <input id="establishment" name="establishment" value="{{ old('establishment', $profile?->establishment) }}" maxlength="255" placeholder="Sucursal, tienda o sede" class="w-full rounded-lg border border-border px-3.5 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20">
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                        <div>
                            <label for="location_id" class="mb-1.5 block text-sm font-medium text-text">Ubicación</label>
                            <select id="location_id" name="location_id" class="w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                                <option value="">Sin ubicación</option>
                                @foreach($locations as $location)<option value="{{ $location->id }}" @selected((string) old('location_id', $profile?->location_id) === (string) $location->id)>{{ $location->name }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label for="group_id" class="mb-1.5 block text-sm font-medium text-text">Grupo</label>
                            <select id="group_id" name="group_id" class="w-full rounded-lg border border-border bg-white px-3.5 py-2.5 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                                <option value="">Sin grupo</option>
                                @foreach($groups as $group)<option value="{{ $group->id }}" @selected((string) old('group_id', $profile?->group_id) === (string) $group->id)>{{ $group->name }}</option>@endforeach
                            </select>
                        </div>
                    </div>
                    <div>
                        <label for="address" class="mb-1.5 block text-sm font-medium text-text">Dirección</label>
                        <input id="address" name="address" value="{{ old('address', $profile?->address) }}" maxlength="500" autocomplete="street-address" placeholder="Calle, número y complemento" class="w-full rounded-lg border border-border px-3.5 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="city" class="mb-1.5 block text-sm font-medium text-text">Ciudad</label>
                            <input id="city" name="city" value="{{ old('city', $profile?->city) }}" maxlength="255" autocomplete="address-level2" class="w-full rounded-lg border border-border px-3.5 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20">
                        </div>
                        <div>
                            <label for="country" class="mb-1.5 block text-sm font-medium text-text">País</label>
                            <input id="country" name="country" value="{{ old('country', $profile?->country) }}" maxlength="255" autocomplete="country-name" class="w-full rounded-lg border border-border px-3.5 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20">
                        </div>
                    </div>

                    <div class="border-t border-border pt-4">
                        <h3 class="text-sm font-semibold text-text">Contacto responsable</h3>
                        <div class="mt-3 space-y-3">
                            <input name="contact_name" value="{{ old('contact_name', $profile?->contact_name) }}" maxlength="255" autocomplete="name" aria-label="Nombre del contacto" placeholder="Nombre" class="w-full rounded-lg border border-border px-3.5 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20">
                            <div class="grid grid-cols-2 gap-3">
                                <input name="contact_phone" value="{{ old('contact_phone', $profile?->contact_phone) }}" maxlength="50" autocomplete="tel" aria-label="Teléfono del contacto" placeholder="Teléfono" class="w-full rounded-lg border border-border px-3.5 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20">
                                <input type="email" name="contact_email" value="{{ old('contact_email', $profile?->contact_email) }}" maxlength="255" autocomplete="email" aria-label="Correo del contacto" placeholder="Correo" class="w-full rounded-lg border border-border px-3.5 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20">
                            </div>
                        </div>
                    </div>

                    <details class="rounded-lg border border-border bg-surface/60 p-3">
                        <summary class="cursor-pointer text-sm font-semibold text-text">Coordenadas y notas</summary>
                        <div class="mt-4 space-y-3">
                            <div class="grid grid-cols-2 gap-3">
                                <div><label for="latitude" class="mb-1 block text-xs text-text-muted">Latitud</label><input id="latitude" name="latitude" type="number" step="0.00000001" min="-90" max="90" value="{{ old('latitude', $profile?->latitude) }}" class="w-full rounded-lg border border-border bg-white px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"></div>
                                <div><label for="longitude" class="mb-1 block text-xs text-text-muted">Longitud</label><input id="longitude" name="longitude" type="number" step="0.00000001" min="-180" max="180" value="{{ old('longitude', $profile?->longitude) }}" class="w-full rounded-lg border border-border bg-white px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"></div>
                            </div>
                            @if($profile?->latitude !== null && $profile?->longitude !== null)
                                <a href="https://www.google.com/maps?q={{ $profile->latitude }},{{ $profile->longitude }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 text-xs font-semibold text-primary hover:underline">Abrir coordenadas en el mapa <span aria-hidden="true">↗</span></a>
                            @endif
                            <div><label for="notes" class="mb-1 block text-xs text-text-muted">Notas</label><textarea id="notes" name="notes" rows="3" maxlength="2000" class="w-full resize-y rounded-lg border border-border bg-white px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">{{ old('notes', $profile?->notes) }}</textarea></div>
                        </div>
                    </details>
                </div>

                <button type="submit" class="mt-5 w-full rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-primary/20 transition hover:bg-primary/90">Guardar información</button>
            </form>
        </aside>
    </div>

    <div x-show="showDeleteModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="delete-video-title">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showDeleteModal = false"></div>
        <div class="relative w-full max-w-md rounded-xl border border-border bg-white p-6 shadow-[0_24px_70px_rgba(15,23,42,0.28)]" @click.outside="showDeleteModal = false">
            <div class="flex items-start gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-danger/10 text-danger"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></div>
                <div><h3 id="delete-video-title" class="text-lg font-semibold text-text">Eliminar video <span x-text="deleteIndex"></span></h3><p class="mt-2 text-sm leading-6 text-text-light">Se eliminará este índice de la tarjeta SD del WL35. Los índices posteriores pueden cambiar después del borrado.</p></div>
            </div>
            <form action="{{ route('devices.wl35.videos.destroy', ['deviceId' => $deviceId]) }}" method="POST" class="mt-6 flex justify-end gap-3" @submit="submitting = true">
                @csrf
                @method('DELETE')
                <input type="hidden" name="index" :value="deleteIndex">
                <button type="button" @click="showDeleteModal = false" class="rounded-lg border border-border px-4 py-2.5 text-sm font-medium text-text transition hover:bg-surface">Cancelar</button>
                <button type="submit" :disabled="submitting" class="rounded-lg bg-danger px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-danger/90 disabled:opacity-50" x-text="submitting ? 'Eliminando…' : 'Eliminar video'"></button>
            </form>
        </div>
    </div>

    <div x-show="showFormatModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="format-sd-title">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showFormatModal = false"></div>
        <div class="relative w-full max-w-md rounded-xl border border-border bg-white p-6 shadow-[0_24px_70px_rgba(15,23,42,0.28)]" @click.outside="showFormatModal = false">
            <div class="flex items-start gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-danger/10 text-danger"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 7h14M9 11v6m6-6v6M8 7l1-3h6l1 3m1 0-1 13H8L7 7"/></svg></div>
                <div><h3 id="format-sd-title" class="text-lg font-semibold text-text">Formatear tarjeta SD</h3><p class="mt-2 text-sm leading-6 text-text-light">El WL35 no ofrece un comando nativo de formateo. El gateway eliminará los {{ $videoCount }} videos uno por uno. Esta acción es irreversible.</p></div>
            </div>
            <form action="{{ route('fleet.command') }}" method="POST" class="mt-6 flex justify-end gap-3" @submit="submitting = true">
                @csrf
                <input type="hidden" name="command" value="format_sd">
                <input type="hidden" name="targets[]" value="{{ $fleetKey }}">
                <button type="button" @click="showFormatModal = false" class="rounded-lg border border-border px-4 py-2.5 text-sm font-medium text-text transition hover:bg-surface">Cancelar</button>
                <button type="submit" :disabled="submitting" class="rounded-lg bg-danger px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-danger/90 disabled:opacity-50" x-text="submitting ? 'Encolando…' : 'Borrar todos los videos'"></button>
            </form>
        </div>
    </div>
</div>
@endsection
