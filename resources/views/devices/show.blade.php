@extends('layouts.app')

@section('title', $device->name)

@section('content')
<div class="max-w-5xl mx-auto" x-data="{ showUnbindModal: false, showRemoveVideoModal: false, showFormatSdModal: false, removeVideoCode: '', removeVideoName: '' }">
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
                        'online', 'active' => ($device->power_status === 'off' ? 'powered_off' : 'online'),
                        'offline', 'inactive', 'error' => ($device->power_status === 'off' ? 'powered_off' : 'offline'),
                        'disabled' => 'disabled',
                        'maintenance' => 'maintenance',
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
            <button @click="showFormatSdModal = true" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-danger/10 text-danger text-sm font-medium hover:bg-danger/20 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
                Formatear SD
            </button>
            <button @click="showUnbindModal = true" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-text-muted/10 text-text-muted text-sm font-medium hover:bg-text-muted/20 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                </svg>
                Desvincular
            </button>
        </div>
    </div>

    <div class="space-y-6">
        <x-device-previews :device-key="'z2:'.strtoupper(str_replace(':', '', $device->mac_address ?? ''))" title="Contenido actual" />

        <!-- Campaign Info -->
        <div class="bg-white rounded-xl border border-border shadow-[0_1px_3px_rgba(0,0,0,0.04)] p-6">
            <h2 class="text-lg font-semibold text-text mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/>
                </svg>
                Campañas Asignadas
            </h2>
            @php
                $targetKey = \App\Services\CampaignTargetCatalog::z2Key($device->mac_address ?? '');
                $hasExplicitCampaigns = \App\Models\Campaign::forTarget($targetKey)->exists();
                $legacyAssignments = $device->deviceCampaigns->filter(fn ($assignment) => $assignment->campaign && $assignment->campaign->target_devices === null);
            @endphp
            @if($hasExplicitCampaigns)
                <x-device-target-campaigns :target-key="$targetKey" />
            @endif
            @if($legacyAssignments->count() > 0)
                <div class="space-y-3">
                    @foreach($legacyAssignments->sortByDesc('started_at')->take(5) as $deviceCampaign)
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
            @elseif(!$hasExplicitCampaigns)
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
        <div class="bg-white rounded-xl border border-border shadow-[0_1px_3px_rgba(0,0,0,0.04)] p-6">
            <h2 class="text-lg font-semibold text-text mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4v16M17 4v16M3 8h4m10 0h4M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z"/>
                </svg>
                Videos del Dispositivo
            </h2>
            @foreach($pendingRemovals as $removal)
                <div class="mb-3 rounded-lg border border-border bg-surface p-3 text-sm" role="status">
                    <p class="font-medium text-text">{{ $removal['filename'] ?? 'Formateo de tarjeta SD' }}</p>
                    <p class="mt-1 text-text-light">
                        @if($removal['status'] === 'expired')
                            No se confirmó el cambio en diez minutos. La lista vuelve a mostrar lo reportado por el dispositivo; revisa su conexión antes de reintentar.
                        @else
                            {{ $removal['filename'] === null ? 'Formateo solicitado' : 'Eliminación solicitada' }}. Pendiente de actualización del dispositivo.
                        @endif
                    </p>
                </div>
            @endforeach
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
                    <p class="text-sm text-text-light">
                        @if(!$playlistAvailable)
                            No se pudo consultar la lista de videos del dispositivo.
                        @elseif(collect($pendingRemovals)->contains('status', 'pending'))
                            Hay cambios pendientes de confirmación.
                        @else
                            El dispositivo no reporta videos.
                        @endif
                    </p>
                </div>
            @endif
        </div>

        <!-- Assign Media Directly -->
        <div class="bg-white rounded-xl border border-border shadow-[0_1px_3px_rgba(0,0,0,0.04)] p-6">
            <h2 class="text-lg font-semibold text-text mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                </svg>
                Asignar Video Directamente
            </h2>
            @if($device->mac_address)
                <x-library-playback :media="$allMediaForDevice->filter(fn ($item) => str_starts_with($item->mime_type, 'video/'))"
                    :device-key="'z2:'.strtoupper(str_replace(':', '', $device->mac_address))" />
            @else
                <p class="text-sm text-text-light">El dispositivo necesita una dirección MAC para reproducir videos.</p>
            @endif
        </div>

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
                <div class="p-4 rounded-lg bg-surface sm:col-span-2 border border-border/60" x-data="{ currentVol: {{ isset($deviceVolume) && $deviceVolume !== null ? $deviceVolume : 50 }} }">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-xs font-medium text-text-muted uppercase tracking-wider flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/>
                            </svg>
                            Control de Volumen de Audio
                        </p>
                        <span class="text-sm font-bold text-primary font-mono" x-text="currentVol + '%'"></span>
                    </div>
                    <form action="{{ route('devices.set-volume', $device) }}" method="POST" class="space-y-3">
                        @csrf
                        <div class="flex items-center gap-3">
                            <button type="button" @click="currentVol = Math.max(0, parseInt(currentVol) - 10)" class="px-2.5 py-1 rounded bg-white border border-border text-xs font-bold text-text hover:bg-surface transition-colors shadow-xs" title="Bajar 10%">-10%</button>
                            <input type="range" name="volume" min="0" max="100" step="1" x-model="currentVol" class="w-full accent-primary cursor-pointer h-2 bg-border/40 rounded-lg">
                            <button type="button" @click="currentVol = Math.min(100, parseInt(currentVol) + 10)" class="px-2.5 py-1 rounded bg-white border border-border text-xs font-bold text-text hover:bg-surface transition-colors shadow-xs" title="Subir 10%">+10%</button>
                        </div>
                        <div class="flex items-center justify-between text-xs text-text-muted pt-1">
                            <div class="flex items-center gap-2">
                                <button type="button" @click="currentVol = 0" class="hover:text-primary transition-colors">Silenciar</button>
                                <span>•</span>
                                <button type="button" @click="currentVol = 50" class="hover:text-primary transition-colors">50%</button>
                                <span>•</span>
                                <button type="button" @click="currentVol = 100" class="hover:text-primary transition-colors">100%</button>
                            </div>
                            <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-primary text-white text-xs font-medium rounded-lg hover:bg-primary/95 transition-colors shadow-xs">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                Aplicar Volumen
                            </button>
                        </div>
                    </form>
                </div>
                <div class="p-4 rounded-lg bg-surface sm:col-span-2" x-data="{ bt: '{{ $deviceBluetooth ?? $device->bluetooth_status ?? 'off' }}' }">
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-medium text-text-muted uppercase tracking-wider flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6.5 7.5L12 12l-5.5 4.5V7.5zM17.5 7.5L12 12l5.5 4.5V7.5zM12 12v6"/>
                            </svg>
                            Bluetooth
                        </p>
                        <form :action="bt === 'on' ? '{{ route('devices.bluetooth-off', $device) }}' : '{{ route('devices.bluetooth-on', $device) }}'" method="POST" class="inline">
                            @csrf
                            <button type="submit" role="switch" :aria-checked="bt === 'on'" title="Bluetooth"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-primary/30"
                                :class="bt === 'on' ? 'bg-primary' : 'bg-border'">
                                <span class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition-transform"
                                    :class="bt === 'on' ? 'translate-x-5' : 'translate-x-0.5'"></span>
                            </button>
                        </form>
                    </div>
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
                    <p class="text-xs font-medium text-text-muted uppercase tracking-wider mb-1">Establecimiento</p>
                    <p class="text-sm text-text">{{ $device->establishmentProfile?->name ?? $device->establishment ?? 'No especificado' }}</p>
                    @if($device->establishmentProfile?->businessType)<p class="mt-0.5 text-xs text-text-light">{{ $device->establishmentProfile->businessType->name }}</p>@endif
                </div>
                <div class="p-4 rounded-lg bg-surface sm:col-span-2">
                    <p class="text-xs font-medium text-text-muted uppercase tracking-wider mb-1">Dirección</p>
                    <p class="text-sm text-text">{{ $device->establishmentProfile?->address ?? $device->address ?? 'No especificada' }}</p>
                    @if(!$device->establishmentProfile && ($device->city || $device->country))
                        <p class="text-xs text-text-light mt-0.5">{{ $device->city ?? '' }}@if($device->city && $device->country), @endif{{ $device->country ?? '' }}</p>
                    @endif
                    @php
                        $mapLatitude = $device->establishmentProfile?->latitude ?? $device->latitude;
                        $mapLongitude = $device->establishmentProfile?->longitude ?? $device->longitude;
                    @endphp
                    @if($mapLatitude !== null && $mapLongitude !== null)
                        <a href="https://www.google.com/maps?q={{ $mapLatitude }},{{ $mapLongitude }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 text-xs text-primary hover:text-primary/80 font-medium transition-colors mt-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.828 0l-4.243-4.243a8 8 0 1111.314 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            Ver en el mapa
                        </a>
                    @endif
                    @if($device->location)
                        <p class="text-xs text-text-muted mt-1">Ubicación: {{ $device->location->name }}</p>
                    @endif
                </div>
                <div class="p-4 rounded-lg bg-surface">
                    <p class="text-xs font-medium text-text-muted uppercase tracking-wider mb-1">Contacto</p>
                    <p class="text-sm text-text">{{ $device->establishmentProfile?->contact_name ?? $device->contact_name ?? 'No especificado' }}</p>
                    @if($device->establishmentProfile?->contact_phone ?? $device->contact_phone)
                        <p class="text-xs text-text-light mt-0.5">{{ $device->establishmentProfile?->contact_phone ?? $device->contact_phone }}</p>
                    @endif
                </div>
                <div class="p-4 rounded-lg bg-surface">
                    <p class="text-xs font-medium text-text-muted uppercase tracking-wider mb-1">Grupo</p>
                    <p class="text-sm text-text">{{ $device->group?->name ?? 'No asignado' }}</p>
                </div>
                <div class="p-4 rounded-lg bg-surface">
                    <p class="text-xs font-medium text-text-muted uppercase tracking-wider mb-1">Último Heartbeat</p>
                    <p class="text-sm text-text">{{ $device->last_heartbeat_at ? $device->last_heartbeat_at->format('d/m/Y H:i') : 'Nunca' }}</p>
                </div>
                <div class="p-4 rounded-lg bg-surface sm:col-span-2">
                    <p class="text-xs font-medium text-text-muted uppercase tracking-wider mb-1">Horas de Trabajo</p>
                    <p class="text-sm text-text">{{ $device->working_hours ? number_format($device->working_hours, 1) . 'h' : '-' }}</p>
                </div>
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
            <p class="text-xs text-text-muted mb-6"><strong>Nota:</strong> El video se quita de la SD del dispositivo (FileDelect). El resto de la lista de reproducción no se altera y el archivo permanece en la biblioteca.</p>
            <div class="flex justify-end gap-3">
                <button @click="showRemoveVideoModal = false" class="px-4 py-2.5 rounded-lg border border-border text-sm font-medium text-text hover:bg-surface transition-colors">Cancelar</button>
                <form action="{{ route('devices.remove-media', $device) }}" method="POST" class="inline">
                    @csrf
                    <input type="hidden" name="ui_code" :value="removeVideoCode">
                    <button type="submit" class="px-4 py-2.5 rounded-lg bg-danger text-white text-sm font-medium hover:bg-danger/90 transition-colors">Quitar Video</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Format SD Modal -->
    <div x-show="showFormatSdModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display: none;">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showFormatSdModal = false"></div>
        <div class="relative bg-white rounded-xl shadow-xl border border-border p-6 w-full max-w-md" @click.away="showFormatSdModal = false">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-danger/10 flex items-center justify-center">
                    <svg class="w-5 h-5 text-danger" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-text">¿Formatear tarjeta SD?</h3>
                </div>
            </div>
            <p class="text-sm text-text-light mb-2">Se borrarán <strong>todos los videos almacenados</strong> en la tarjeta SD del dispositivo.</p>
            <p class="text-xs text-text-muted mb-6"><strong>Nota:</strong> El dispositivo permanecerá vinculado a tu cuenta Z2 Cloud y podrás volver a asignarle videos en cualquier momento.</p>
            <div class="flex justify-end gap-3">
                <button @click="showFormatSdModal = false" class="px-4 py-2.5 rounded-lg border border-border text-sm font-medium text-text hover:bg-surface transition-colors">Cancelar</button>
                <form action="{{ route('devices.format-sd', $device) }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="px-4 py-2.5 rounded-lg bg-danger text-white text-sm font-medium hover:bg-danger/90 transition-colors">Formatear SD</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
