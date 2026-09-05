@extends('layouts.app')

@section('title', 'Flota unificada')

@section('content')
@php
    $connectedKeys = $devices
        ->filter(fn ($device) => ($device['online'] ?? false) && ($device['connected'] ?? false))
        ->pluck('key')
        ->values();
    $initialTargets = collect(old('targets', []))->intersect($devices->pluck('key'))->values();
    $wl35Count = $devices->where('type', 'wl35')->count();
    $z2Count = $devices->where('type', 'z2')->count();
    $onlineCount = $connectedKeys->count();
@endphp

<div
    x-data="{
        selected: {{ Js::from($initialTargets) }},
        connected: {{ Js::from($connectedKeys) }},
        search: '',
        type: 'all',
        submitting: false,
        bulkCommand(command, value) {
            if (!this.selected.length || this.submitting) return;
            this.$refs.bulkCommand.querySelector('[name=command]').value = command;
            this.$refs.bulkCommand.querySelector('[name=value]').value = value;
            this.submitting = true;
            this.$refs.bulkCommand.submit();
        },
        selectConnected() {
            this.selected = [...this.connected];
        }
    }"
    class="space-y-6"
>
    <section class="relative overflow-hidden rounded-2xl bg-slate-950 px-6 py-7 text-white shadow-xl shadow-slate-900/10 sm:px-8">
        <div class="absolute -right-20 -top-24 h-64 w-64 rounded-full bg-secondary/20 blur-3xl"></div>
        <div class="absolute bottom-0 right-1/3 h-px w-1/3 bg-gradient-to-r from-transparent via-secondary to-transparent"></div>
        <div class="relative flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-2xl">
                <div class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.2em] text-sky-300">
                    <span class="h-2 w-2 rounded-full bg-sky-400"></span>
                    Centro de control WL35 + Z2
                </div>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Una flota, dos protocolos</h2>
                <p class="mt-2 max-w-xl text-sm leading-6 text-slate-300">
                    Selecciona cualquier combinación de ventiladores y envía la misma orden desde un solo lugar.
                </p>
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div class="min-w-24 rounded-xl border border-white/10 bg-white/5 px-4 py-3 backdrop-blur">
                    <div class="text-2xl font-bold text-emerald-400">{{ $onlineCount }}</div>
                    <div class="text-[10px] uppercase tracking-widest text-slate-400">Conectados</div>
                </div>
                <div class="min-w-20 rounded-xl border border-white/10 bg-white/5 px-4 py-3 backdrop-blur">
                    <div class="text-2xl font-bold text-sky-400">{{ $wl35Count }}</div>
                    <div class="text-[10px] uppercase tracking-widest text-slate-400">WL35</div>
                </div>
                <div class="min-w-20 rounded-xl border border-white/10 bg-white/5 px-4 py-3 backdrop-blur">
                    <div class="text-2xl font-bold text-amber-400">{{ $z2Count }}</div>
                    <div class="text-[10px] uppercase tracking-widest text-slate-400">Z2</div>
                </div>
            </div>
        </div>
    </section>

    @if($gatewayError)
        <div class="rounded-xl border border-danger/25 bg-danger/5 px-5 py-4 text-sm text-danger">
            <div class="font-semibold">No se pudo consultar el gateway unificado</div>
            <div class="mt-1 text-danger/80">{{ $gatewayError }}</div>
        </div>
    @elseif(!$gatewayConfigured)
        <div class="rounded-xl border border-warning/30 bg-warning/10 px-5 py-4 text-sm text-amber-800">
            La consulta está disponible, pero las órdenes requieren configurar <code class="font-semibold">UNIFIED_FLEET_API_TOKEN</code>.
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-xl border border-danger/25 bg-danger/5 px-5 py-4 text-sm text-danger">
            <div class="font-semibold">Revisa la solicitud:</div>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if(session('fleet_results'))
        <div class="rounded-xl border border-border bg-white p-4 shadow-[0_1px_3px_rgba(0,0,0,0.04)]">
            <div class="mb-3 text-xs font-semibold uppercase tracking-wider text-text-muted">Resultado por equipo</div>
            <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                @foreach(session('fleet_results') as $result)
                    <div class="flex items-start gap-3 rounded-lg border px-3 py-2.5 {{ ($result['success'] ?? false) ? 'border-success/20 bg-success/5' : 'border-danger/20 bg-danger/5' }}">
                        <span class="mt-1 h-2 w-2 shrink-0 rounded-full {{ ($result['success'] ?? false) ? 'bg-success' : 'bg-danger' }}"></span>
                        <div class="min-w-0">
                            <div class="truncate text-xs font-semibold text-text">{{ $result['key'] ?? $result['id'] ?? 'Equipo' }}</div>
                            <div class="mt-0.5 text-xs text-text-light">
                                {{ ($result['success'] ?? false) ? 'Orden aceptada' : ($result['error'] ?? 'La operación falló') }}
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <section class="sticky top-[73px] z-20 rounded-2xl border border-border bg-white/95 p-4 shadow-lg shadow-slate-900/5 backdrop-blur sm:p-5">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-center">
            <div class="flex min-w-48 items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-primary/10 text-lg font-bold text-primary" x-text="selected.length">0</div>
                <div>
                    <div class="text-sm font-semibold text-text">Equipos seleccionados</div>
                    <div class="text-xs text-text-muted">Las acciones aplican a esta selección</div>
                </div>
            </div>

            <div class="flex flex-wrap gap-2 xl:ml-auto">
                <button type="button" @click="selectConnected()" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-text-light transition hover:border-primary/30 hover:bg-primary/5 hover:text-primary">
                    Seleccionar conectados
                </button>
                <button type="button" @click="selected = []" :disabled="!selected.length" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-text-light transition hover:bg-surface-dark disabled:cursor-not-allowed disabled:opacity-40">
                    Limpiar
                </button>
                <span class="hidden h-9 w-px bg-border sm:block"></span>
                <button type="button" @click="bulkCommand('power', '1')" :disabled="!selected.length || submitting" class="rounded-lg bg-success px-3 py-2 text-xs font-semibold text-white transition hover:bg-success/90 disabled:cursor-not-allowed disabled:opacity-40">Encender</button>
                <button type="button" @click="bulkCommand('power', '0')" :disabled="!selected.length || submitting" class="rounded-lg bg-danger px-3 py-2 text-xs font-semibold text-white transition hover:bg-danger/90 disabled:cursor-not-allowed disabled:opacity-40">Apagar</button>
                <button type="button" @click="bulkCommand('bluetooth', '1')" :disabled="!selected.length || submitting" class="rounded-lg border border-primary/25 bg-primary/5 px-3 py-2 text-xs font-semibold text-primary transition hover:bg-primary/10 disabled:cursor-not-allowed disabled:opacity-40">Bluetooth ON</button>
                <button type="button" @click="bulkCommand('bluetooth', '0')" :disabled="!selected.length || submitting" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-text-light transition hover:bg-surface-dark disabled:cursor-not-allowed disabled:opacity-40">Bluetooth OFF</button>
            </div>
        </div>

        <form x-ref="bulkCommand" method="POST" action="{{ route('fleet.command') }}" class="hidden">
            @csrf
            <input type="hidden" name="command">
            <input type="hidden" name="value">
            <template x-for="target in selected" :key="target">
                <input type="hidden" name="targets[]" :value="target">
            </template>
        </form>
    </section>

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="rounded-2xl border border-border bg-white p-5 shadow-[0_1px_3px_rgba(0,0,0,0.04)] sm:p-6">
            <div class="mb-5 flex items-start gap-4">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-secondary/10 text-secondary">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5.002 5.002 0 0115.9 6H16a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                </div>
                <div>
                    <h3 class="font-semibold text-text">Subir y distribuir video</h3>
                    <p class="mt-1 text-sm leading-5 text-text-light">El archivo se carga una vez al gateway y se adapta para cada protocolo seleccionado.</p>
                </div>
            </div>
            <form method="POST" action="{{ route('fleet.upload') }}" enctype="multipart/form-data" @submit="submitting = true">
                @csrf
                <template x-for="target in selected" :key="'upload-' + target">
                    <input type="hidden" name="targets[]" :value="target">
                </template>
                <label class="block cursor-pointer rounded-xl border-2 border-dashed border-border bg-surface px-5 py-6 text-center transition hover:border-secondary/40 hover:bg-secondary/5">
                    <svg class="mx-auto h-7 w-7 text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    <span class="mt-2 block text-sm font-semibold text-text">Seleccionar MP4</span>
                    <span class="mt-1 block text-xs text-text-muted">Máximo 250 MB</span>
                    <input type="file" name="video" accept="video/mp4,.mp4" required class="mt-3 block w-full text-xs text-text-light file:mr-3 file:rounded-lg file:border-0 file:bg-primary file:px-3 file:py-2 file:font-semibold file:text-white hover:file:bg-primary/90">
                </label>
                <button type="submit" :disabled="!selected.length || submitting" class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 py-3 text-sm font-semibold text-white shadow-sm shadow-primary/20 transition hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-40">
                    <span x-text="submitting ? 'Procesando…' : 'Distribuir a la selección'"></span>
                    <span x-show="!submitting" class="rounded bg-white/15 px-1.5 py-0.5 text-[10px]" x-text="selected.length"></span>
                </button>
            </form>
        </section>

        <section class="rounded-2xl border border-border bg-white p-5 shadow-[0_1px_3px_rgba(0,0,0,0.04)] sm:p-6">
            <div class="mb-5 flex items-start gap-4">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-accent/10 text-accent">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <h3 class="font-semibold text-text">Reproducción inmediata</h3>
                    <p class="mt-1 text-sm leading-5 text-text-light">WL35 usa un índice de biblioteca; Z2 usa el nombre del archivo en la nube privada.</p>
                </div>
            </div>
            <form method="POST" action="{{ route('fleet.command') }}" @submit="submitting = true" class="space-y-4">
                @csrf
                <input type="hidden" name="command" value="play">
                <template x-for="target in selected" :key="'play-' + target">
                    <input type="hidden" name="targets[]" :value="target">
                </template>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block">
                        <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-text-muted">WL35 · índice</span>
                        <input type="number" name="wl35_video_index" value="{{ old('wl35_video_index', 1) }}" min="1" max="255" class="w-full rounded-lg border border-border px-3 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/15">
                    </label>
                    <label class="block">
                        <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-text-muted">Z2 · archivo</span>
                        <select name="z2_filename" class="w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/15">
                            <option value="">Seleccionar archivo</option>
                            @foreach($media as $asset)
                                <option value="{{ $asset['filename'] ?? '' }}" @selected(old('z2_filename') === ($asset['filename'] ?? null))>{{ $asset['filename'] ?? 'Archivo' }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
                <button type="submit" :disabled="!selected.length || submitting" class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-slate-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-40">
                    Reproducir en la selección
                </button>
            </form>
        </section>
    </div>

    <section>
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-lg font-bold text-text">Ventiladores conectados</h3>
                <p class="mt-1 text-sm text-text-light">Control individual y selección para órdenes masivas.</p>
            </div>
            <div class="flex gap-2">
                <div class="relative">
                    <svg class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input x-model="search" type="search" placeholder="Buscar equipo…" class="w-48 rounded-lg border border-border py-2 pl-9 pr-3 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/15">
                </div>
                <select x-model="type" class="rounded-lg border border-border bg-white px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/15">
                    <option value="all">Todos</option>
                    <option value="wl35">WL35</option>
                    <option value="z2">Z2</option>
                </select>
                <a href="{{ route('fleet.index') }}" class="inline-flex items-center rounded-lg border border-border px-3 py-2 text-sm font-medium text-text-light transition hover:bg-surface-dark" title="Actualizar estados">
                    Actualizar
                </a>
            </div>
        </div>

        @if($devices->isEmpty())
            <div class="rounded-2xl border border-dashed border-border bg-white px-6 py-14 text-center">
                <div class="text-sm font-semibold text-text">No hay ventiladores disponibles</div>
                <div class="mt-1 text-sm text-text-muted">Revisa la conexión del gateway WL35 y de la nube privada Z2.</div>
            </div>
        @else
            <div class="grid gap-4 lg:grid-cols-2 2xl:grid-cols-3">
                @foreach($devices as $device)
                    @php
                        $key = $device['key'] ?? '';
                        $type = $device['type'] ?? 'unknown';
                        $isConnected = ($device['online'] ?? false) && ($device['connected'] ?? false);
                        $searchable = strtolower(implode(' ', [$device['name'] ?? '', $device['id'] ?? '', $device['ip'] ?? '']));
                    @endphp
                    <article
                        x-show="(type === 'all' || type === '{{ $type }}') && (!search || {{ Js::from($searchable) }}.includes(search.toLowerCase()))"
                        x-transition.opacity
                        class="overflow-hidden rounded-2xl border bg-white shadow-[0_1px_3px_rgba(0,0,0,0.04)] transition"
                        :class="selected.includes({{ Js::from($key) }}) ? 'border-primary ring-2 ring-primary/10' : 'border-border'"
                    >
                        <div class="flex items-start gap-4 border-b border-border p-5">
                            <label class="mt-1 flex cursor-pointer items-center">
                                <input type="checkbox" value="{{ $key }}" x-model="selected" class="h-4 w-4 rounded border-border text-primary focus:ring-primary">
                            </label>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="rounded-md px-2 py-1 text-[10px] font-bold uppercase tracking-widest {{ $type === 'wl35' ? 'bg-sky-100 text-sky-700' : 'bg-amber-100 text-amber-700' }}">{{ strtoupper($type) }}</span>
                                    <span class="inline-flex items-center gap-1 text-[10px] font-semibold uppercase tracking-wider {{ $isConnected ? 'text-success' : 'text-text-muted' }}">
                                        <span class="h-1.5 w-1.5 rounded-full {{ $isConnected ? 'bg-success' : 'bg-text-muted' }}"></span>
                                        {{ $isConnected ? 'Conectado' : 'Desconectado' }}
                                    </span>
                                </div>
                                <h4 class="mt-2 truncate text-base font-bold text-text">{{ $device['name'] ?? $device['id'] ?? 'Ventilador' }}</h4>
                                <div class="mt-1 truncate font-mono text-xs text-text-muted">{{ $device['id'] ?? '' }}</div>
                            </div>
                            <div class="rounded-lg px-2.5 py-1 text-xs font-semibold {{ ($device['power'] ?? false) ? 'bg-success/10 text-success' : 'bg-surface-dark text-text-muted' }}">
                                {{ ($device['power'] ?? false) ? 'ON' : 'OFF' }}
                            </div>
                        </div>

                        <div class="grid grid-cols-3 divide-x divide-border border-b border-border bg-surface/70">
                            <div class="px-3 py-3 text-center">
                                <div class="text-[10px] uppercase tracking-wider text-text-muted">Bluetooth</div>
                                <div class="mt-1 text-xs font-semibold {{ ($device['bluetooth'] ?? false) ? 'text-primary' : 'text-text-light' }}">{{ ($device['bluetooth'] ?? false) ? 'Activo' : 'Inactivo' }}</div>
                            </div>
                            <div class="px-3 py-3 text-center">
                                <div class="text-[10px] uppercase tracking-wider text-text-muted">Video</div>
                                <div class="mt-1 truncate text-xs font-semibold text-text" title="{{ $device['current_video'] ?? '—' }}">{{ $device['current_video'] ?? '—' }}</div>
                            </div>
                            <div class="px-3 py-3 text-center">
                                <div class="text-[10px] uppercase tracking-wider text-text-muted">Biblioteca</div>
                                <div class="mt-1 text-xs font-semibold text-text">{{ $device['video_count'] ?? 0 }}</div>
                            </div>
                        </div>

                        <div class="space-y-3 p-4">
                            <div class="grid grid-cols-2 gap-2">
                                <form method="POST" action="{{ route('fleet.command') }}" class="grid grid-cols-2 gap-1">
                                    @csrf
                                    <input type="hidden" name="command" value="power">
                                    <input type="hidden" name="targets[]" value="{{ $key }}">
                                    <button name="value" value="1" type="submit" {{ $isConnected ? '' : 'disabled' }} class="rounded-lg border border-success/25 bg-success/5 px-2 py-2 text-[11px] font-semibold text-success transition hover:bg-success/10 disabled:opacity-40">Encender</button>
                                    <button name="value" value="0" type="submit" {{ $isConnected ? '' : 'disabled' }} class="rounded-lg border border-danger/20 bg-danger/5 px-2 py-2 text-[11px] font-semibold text-danger transition hover:bg-danger/10 disabled:opacity-40">Apagar</button>
                                </form>
                                <form method="POST" action="{{ route('fleet.command') }}" class="grid grid-cols-2 gap-1">
                                    @csrf
                                    <input type="hidden" name="command" value="bluetooth">
                                    <input type="hidden" name="targets[]" value="{{ $key }}">
                                    <button name="value" value="1" type="submit" {{ $isConnected ? '' : 'disabled' }} class="rounded-lg border border-primary/20 bg-primary/5 px-2 py-2 text-[11px] font-semibold text-primary transition hover:bg-primary/10 disabled:opacity-40">BT ON</button>
                                    <button name="value" value="0" type="submit" {{ $isConnected ? '' : 'disabled' }} class="rounded-lg border border-border px-2 py-2 text-[11px] font-semibold text-text-light transition hover:bg-surface-dark disabled:opacity-40">BT OFF</button>
                                </form>
                            </div>

                            <form method="POST" action="{{ route('fleet.command') }}" class="flex gap-2">
                                @csrf
                                <input type="hidden" name="command" value="play">
                                <input type="hidden" name="targets[]" value="{{ $key }}">
                                @if($type === 'wl35')
                                    <input type="number" name="wl35_video_index" min="1" max="255" value="1" aria-label="Índice de video WL35" class="min-w-0 flex-1 rounded-lg border border-border px-3 py-2 text-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/15">
                                @else
                                    <select name="z2_filename" aria-label="Archivo Z2" class="min-w-0 flex-1 rounded-lg border border-border bg-white px-3 py-2 text-xs outline-none focus:border-primary focus:ring-2 focus:ring-primary/15">
                                        <option value="">Seleccionar video</option>
                                        @foreach($media as $asset)
                                            <option value="{{ $asset['filename'] ?? '' }}">{{ $asset['filename'] ?? 'Archivo' }}</option>
                                        @endforeach
                                    </select>
                                @endif
                                <button type="submit" {{ $isConnected ? '' : 'disabled' }} class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white transition hover:bg-slate-800 disabled:opacity-40">Reproducir</button>
                            </form>
                            <div class="flex items-center justify-between text-[11px] text-text-muted">
                                <span>{{ $device['ip'] ?? 'IP no disponible' }}</span>
                                @if($device['uploading'] ?? false)
                                    <span class="font-semibold text-warning">Carga en curso</span>
                                @elseif(!($device['session_ready'] ?? true))
                                    <span class="font-semibold text-warning">Sesión no lista</span>
                                @endif
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    <section class="grid gap-3 sm:grid-cols-2">
        @php
            $wl35Source = $sources['wl35'] ?? [];
            $z2Source = $sources['private_cloud'] ?? [];
        @endphp
        <div class="flex items-center justify-between rounded-xl border border-border bg-white px-4 py-3 text-sm">
            <div><span class="font-semibold text-text">Gateway WL35</span><span class="ml-2 text-text-muted">{{ $wl35Source['devices'] ?? 0 }} equipos</span></div>
            <span class="font-semibold {{ ($wl35Source['ok'] ?? false) ? 'text-success' : 'text-danger' }}">{{ ($wl35Source['ok'] ?? false) ? 'Disponible' : 'Sin conexión' }}</span>
        </div>
        <div class="flex items-center justify-between rounded-xl border border-border bg-white px-4 py-3 text-sm">
            <div><span class="font-semibold text-text">Nube privada Z2</span><span class="ml-2 text-text-muted">{{ $z2Source['devices'] ?? 0 }} equipos</span></div>
            <span class="font-semibold {{ ($z2Source['ok'] ?? false) ? 'text-success' : 'text-danger' }}">{{ ($z2Source['ok'] ?? false) ? 'Disponible' : 'Sin conexión' }}</span>
        </div>
    </section>
</div>
@endsection
