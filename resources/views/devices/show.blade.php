@extends('layouts.app')

@section('title', $device->name)

@section('content')
<div class="max-w-5xl mx-auto" x-data="{ showUnbindModal: false, showRemoveVideoModal: false, removeVideoCode: '', removeVideoName: '' }">
    <!-- Page Header -->
    <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-sm text-text-light mb-2">
                <a href="{{ route('devices.index') }}" class="hover:text-primary transition-colors">Dispositivos</a>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
                <span class="text-text">{{ $device->name }}</span>
            </div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-text">{{ $device->name }}</h1>
                @php
                    $indicatorStatus = match($device->status) {
                        'active' => 'online',
                        'inactive' => 'offline',
                        'error' => 'offline',
                        'disabled' => 'disabled',
                        default => 'offline',
                    };
                @endphp
                <x-device-status-indicator :status="$indicatorStatus" />
            </div>
        </div>
        <div class="flex items-center gap-2">
            <form action="{{ route('devices.power-on', $device) }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-success/10 text-success text-sm font-medium hover:bg-success/20 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    Encender
                </button>
            </form>
            <form action="{{ route('devices.power-off', $device) }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-warning/10 text-warning text-sm font-medium hover:bg-warning/20 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                    </svg>
                    Apagar
                </button>
            </form>
            @if($device->status !== 'disabled')
                <form action="{{ route('devices.disable', $device) }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-danger/10 text-danger text-sm font-medium hover:bg-danger/20 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Deshabilitar
                    </button>
                </form>
            @else
                <form action="{{ route('devices.enable', $device) }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-success/10 text-success text-sm font-medium hover:bg-success/20 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Habilitar
                    </button>
                </form>
            @endif
            <button @click="showUnbindModal = true" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-text-muted/10 text-text-muted text-sm font-medium hover:bg-text-muted/20 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                </svg>
                Desvincular
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Device Details -->
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-xl border border-border shadow-[0_1px_3px_rgba(0,0,0,0.04)] p-6">
                <h2 class="text-lg font-semibold text-text mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Información del Dispositivo
                </h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="p-4 rounded-lg bg-surface">
                        <p class="text-xs font-medium text-text-muted uppercase tracking-wider mb-1">MAC Address</p>
                        <p class="text-sm text-text font-mono">{{ $device->mac_address }}</p>
                    </div>
                    <div class="p-4 rounded-lg bg-surface">
                        <p class="text-xs font-medium text-text-muted uppercase tracking-wider mb-1">Firmware</p>
                        <p class="text-sm text-text">{{ $device->firmware ?? 'No especificado' }}</p>
                    </div>
                    <div class="p-4 rounded-lg bg-surface">
                        <p class="text-xs font-medium text-text-muted uppercase tracking-wider mb-1">Hardware</p>
                        <p class="text-sm text-text">{{ $device->hardware ?? 'No especificado' }}</p>
                    </div>
                    <div class="p-4 rounded-lg bg-surface">
                        <p class="text-xs font-medium text-text-muted uppercase tracking-wider mb-1">RPM</p>
                        <p class="text-sm text-text font-mono">{{ $device->rpm ?? '-' }}</p>
                    </div>
                    <div class="p-4 rounded-lg bg-surface">
                        <p class="text-xs font-medium text-text-muted uppercase tracking-wider mb-1">Ubicación</p>
                        <p class="text-sm text-text">{{ $device->location?->name ?? 'No asignada' }}</p>
                    </div>
                    <div class="p-4 rounded-lg bg-surface">
                        <p class="text-xs font-medium text-text-muted uppercase tracking-wider mb-1">Grupo</p>
                        <p class="text-sm text-text">{{ $device->group?->name ?? 'No asignado' }}</p>
                    </div>
                    <div class="p-4 rounded-lg bg-surface">
                        <p class="text-xs font-medium text-text-muted uppercase tracking-wider mb-1">Último Heartbeat</p>
                        <p class="text-sm text-text">{{ $device->last_heartbeat_at ? $device->last_heartbeat_at->format('d/m/Y H:i') : 'Nunca' }}</p>
                    </div>
                    <div class="p-4 rounded-lg bg-surface">
                        <p class="text-xs font-medium text-text-muted uppercase tracking-wider mb-1">Horas de Trabajo</p>
                        <p class="text-sm text-text">{{ $device->working_hours ? number_format($device->working_hours, 1) . 'h' : '-' }}</p>
                    </div>
                    @if($deviceDetail)
                        <div class="p-4 rounded-lg bg-surface sm:col-span-2">
                            <p class="text-xs font-medium text-text-muted uppercase tracking-wider mb-1">Información de Z2 Cloud</p>
                            <pre class="text-xs text-text-light font-mono overflow-x-auto">{{ json_encode($deviceDetail, JSON_PRETTY_PRINT) }}</pre>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Status History -->
            <div class="bg-white rounded-xl border border-border shadow-[0_1px_3px_rgba(0,0,0,0.04)] p-6">
                <h2 class="text-lg font-semibold text-text mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Historial de Heartbeats
                </h2>
                @if($device->heartbeats->count() > 0)
                    <div class="space-y-4">
                        @foreach($device->heartbeats->sortByDesc('received_at')->take(10) as $heartbeat)
                            <div class="flex items-center gap-4 p-3 rounded-lg bg-surface">
                                <div class="w-2 h-2 rounded-full
                                    @if($heartbeat->status === 'active') bg-success
                                    @elseif($heartbeat->status === 'error') bg-danger
                                    @else bg-text-muted
                                    @endif"></div>
                                <div class="flex-1">
                                    <p class="text-sm text-text">{{ $heartbeat->received_at->format('d/m/Y H:i:s') }}</p>
                                    <p class="text-xs text-text-light">RPM: {{ $heartbeat->rpm ?? '-' }}</p>
                                </div>
                                <span class="text-xs font-medium px-2 py-1 rounded-full
                                    @if($heartbeat->status === 'active') bg-success/10 text-success
                                    @elseif($heartbeat->status === 'error') bg-danger/10 text-danger
                                    @else bg-text-muted/10 text-text-muted
                                    @endif">
                                    {{ $heartbeat->status === 'active' ? 'Activo' : ($heartbeat->status === 'error' ? 'Error' : 'Inactivo') }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-8">
                        <p class="text-sm text-text-light">No hay registros de heartbeats</p>
                    </div>
                @endif
            </div>

            <!-- Campaign Info -->
            <div class="bg-white rounded-xl border border-border shadow-[0_1px_3px_rgba(0,0,0,0.04)] p-6">
                <h2 class="text-lg font-semibold text-text mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/>
                    </svg>
                    Campañas Asignadas
                </h2>
                @if($device->deviceCampaigns->count() > 0)
                    <div class="space-y-3">
                        @foreach($device->deviceCampaigns->sortByDesc('started_at')->take(5) as $deviceCampaign)
                            <div class="p-4 rounded-lg bg-surface border border-border">
                                <div class="flex items-center justify-between mb-2">
                                    <h3 class="font-semibold text-text">{{ $deviceCampaign->campaign?->name ?? 'Sin nombre' }}</h3>
                                    <x-campaign-status-badge :status="$deviceCampaign->status" />
                                </div>
                                <p class="text-sm text-text-light mb-2">{{ $deviceCampaign->campaign?->description ?? 'Sin descripción' }}</p>
                                <div class="flex items-center gap-4 text-xs text-text-muted">
                                    <span>Inicio: {{ $deviceCampaign->started_at ? $deviceCampaign->started_at->format('d/m/Y H:i') : '-' }}</span>
                                    @if($deviceCampaign->finished_at)
                                        <span>Fin: {{ $deviceCampaign->finished_at->format('d/m/Y H:i') }}</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-8">
                        <div class="w-12 h-12 rounded-xl bg-surface flex items-center justify-center mx-auto mb-3">
                            <svg class="w-6 h-6 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/>
                            </svg>
                        </div>
                        <p class="text-sm text-text-light">No hay campañas asignadas</p>
                    </div>
                @endif
            </div>

            <!-- Current Device Playlist -->
            <div class="bg-white rounded-xl border border-border shadow-[0_1px_3px_rgba(0,0,0,0.04)] p-6 mt-6">
                <h2 class="text-lg font-semibold text-text mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4v16M17 4v16M3 8h4m10 0h4M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z"/>
                    </svg>
                    Videos del Dispositivo
                </h2>
                @if($devicePlaylist && count($devicePlaylist) > 0)
                    <div class="space-y-2">
                        @foreach($devicePlaylist as $playlistItem)
                            @php
                                $playlistMedia = \App\Models\Media::where('file_path', $playlistItem)->first();
                            @endphp
                            <div class="flex items-center justify-between p-3 rounded-lg bg-surface border border-border">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center flex-shrink-0">
                                        <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-text truncate">{{ $playlistMedia ? $playlistMedia->name : $playlistItem }}</p>
                                        @if($playlistMedia && $playlistMedia->duration)
                                            <p class="text-xs text-text-muted">{{ gmdate('i:s', $playlistMedia->duration) }}</p>
                                        @else
                                            <p class="text-xs text-text-muted font-mono">{{ $playlistItem }}</p>
                                        @endif
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    @click="showRemoveVideoModal = true; removeVideoCode = '{{ $playlistItem }}'; removeVideoName = '{{ $playlistMedia ? addslashes($playlistMedia->name) : $playlistItem }}'"
                                    class="flex-shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-danger/10 text-danger text-xs font-medium hover:bg-danger/20 transition-colors ml-3"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                    Quitar
                                </button>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-6">
                        <div class="w-10 h-10 rounded-xl bg-surface flex items-center justify-center mx-auto mb-2">
                            <svg class="w-5 h-5 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4v16M17 4v16M3 8h4m10 0h4M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z"/>
                            </svg>
                        </div>
                        <p class="text-sm text-text-light">No hay videos asignados a este dispositivo</p>
                    </div>
                @endif
            </div>

            <!-- Assign Media Directly -->
            <div class="bg-white rounded-xl border border-border shadow-[0_1px_3px_rgba(0,0,0,0.04)] p-6 mt-6">
                <h2 class="text-lg font-semibold text-text mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                    </svg>
                    Asignar Video Directamente
                </h2>
                <form action="{{ route('devices.assign-media', $device) }}" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <label for="media_id" class="block text-sm font-medium text-text mb-1.5">Seleccionar Video</label>
                        <select name="media_id" id="media_id" required class="w-full rounded-lg border border-border bg-surface px-4 py-2.5 text-sm text-text focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-colors">
                            <option value="">-- Seleccione un video --</option>
                            @foreach($allMediaForDevice as $mediaItem)
                                <option value="{{ $mediaItem->id }}">{{ $mediaItem->name }} ({{ $mediaItem->duration ? gmdate('i:s', $mediaItem->duration) : '00:00' }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-primary text-white text-sm font-medium hover:bg-primary/95 transition-colors shadow-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            Asignar Video
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Mini Chart -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl border border-border shadow-[0_1px_3px_rgba(0,0,0,0.04)] p-6 sticky top-6">
                <h2 class="text-lg font-semibold text-text mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                    RPM (últimos 10)
                </h2>
                @if($device->heartbeats->count() > 0)
                    <div class="space-y-3">
                        @foreach($device->heartbeats->sortByDesc('received_at')->take(10) as $heartbeat)
                            <div class="flex items-center gap-3">
                                <span class="text-xs text-text-muted w-12">{{ $heartbeat->rpm ?? 0 }}</span>
                                <div class="flex-1 h-4 bg-surface rounded-full overflow-hidden">
                                    <div class="h-full bg-primary rounded-full transition-all" style="width: {{ min(($heartbeat->rpm ?? 0) / 30 * 100, 100) }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-8">
                        <p class="text-sm text-text-light">No hay datos de RPM</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Unbind Modal -->
    <div x-show="showUnbindModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display: none;">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showUnbindModal = false"></div>
        <div class="relative bg-white rounded-xl shadow-xl border border-border p-6 w-full max-w-md" @click.away="showUnbindModal = false">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-warning/10 flex items-center justify-center">
                    <svg class="w-5 h-5 text-warning" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-text">¿Desvincular dispositivo?</h3>
                </div>
            </div>
            <p class="text-sm text-text-light mb-6">Se desvinculará el contenido actual de este dispositivo. ¿Deseas continuar?</p>
            <div class="flex justify-end gap-3">
                <button @click="showUnbindModal = false" class="px-4 py-2.5 rounded-lg border border-border text-sm font-medium text-text hover:bg-surface transition-colors">Cancelar</button>
                <form action="{{ route('devices.unbind', $device) }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="px-4 py-2.5 rounded-lg bg-warning text-white text-sm font-medium hover:bg-amber-600 transition-colors">Desvincular</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Remove Video Modal -->
    <div x-show="showRemoveVideoModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display: none;">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showRemoveVideoModal = false"></div>
        <div class="relative bg-white rounded-xl shadow-xl border border-border p-6 w-full max-w-md" @click.away="showRemoveVideoModal = false">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-danger/10 flex items-center justify-center">
                    <svg class="w-5 h-5 text-danger" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-text">¿Quitar video del dispositivo?</h3>
                </div>
            </div>
            <p class="text-sm text-text-light mb-1">Se quitará el siguiente video de la lista de reproducción del dispositivo:</p>
            <p class="text-sm font-medium text-text mb-2" x-text="removeVideoName"></p>
            <p class="text-xs text-text-muted mb-6"><strong>Nota:</strong> Se formatea la SD del dispositivo y se re-asignan los videos restantes. El dispositivo descargará nuevamente los videos.</p>
            <div class="flex justify-end gap-3">
                <button @click="showRemoveVideoModal = false" class="px-4 py-2.5 rounded-lg border border-border text-sm font-medium text-text hover:bg-surface transition-colors">Cancelar</button>
                <form action="{{ route('devices.remove-media', $device) }}" method="POST" class="inline">
                    @csrf
                    <input type="hidden" name="ui_code" :value="removeVideoCode">
                    <button type="submit" class="px-4 py-2.5 rounded-lg bg-danger text-white text-sm font-medium hover:bg-red-700 transition-colors">Quitar Video</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
