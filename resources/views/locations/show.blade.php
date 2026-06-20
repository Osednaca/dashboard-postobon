@extends('layouts.app')

@section('title', $location->name)

@section('content')
<div class="max-w-5xl mx-auto">
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center gap-2 text-sm text-text-light mb-2">
            <a href="{{ route('locations.index') }}" class="hover:text-primary transition-colors">Ubicaciones</a>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
            <span class="text-text">{{ $location->name }}</span>
        </div>
        <h1 class="text-2xl font-bold text-text">{{ $location->name }}</h1>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Details Card -->
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-xl border border-border shadow-[0_1px_3px_rgba(0,0,0,0.04)] p-6">
                <h2 class="text-lg font-semibold text-text mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Información General
                </h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="p-4 rounded-lg bg-surface">
                        <p class="text-xs font-medium text-text-muted uppercase tracking-wider mb-1">Dirección</p>
                        <p class="text-sm text-text">{{ $location->address ?? 'No especificada' }}</p>
                    </div>
                    <div class="p-4 rounded-lg bg-surface">
                        <p class="text-xs font-medium text-text-muted uppercase tracking-wider mb-1">Ciudad</p>
                        <p class="text-sm text-text">{{ $location->city ?? 'No especificada' }}</p>
                    </div>
                    <div class="p-4 rounded-lg bg-surface">
                        <p class="text-xs font-medium text-text-muted uppercase tracking-wider mb-1">País</p>
                        <p class="text-sm text-text">{{ $location->country ?? 'No especificado' }}</p>
                    </div>
                    <div class="p-4 rounded-lg bg-surface">
                        <p class="text-xs font-medium text-text-muted uppercase tracking-wider mb-1">Coordenadas</p>
                        <p class="text-sm text-text">{{ $location->latitude ? $location->latitude . ', ' . $location->longitude : 'No especificadas' }}</p>
                    </div>
                    <div class="p-4 rounded-lg bg-surface">
                        <p class="text-xs font-medium text-text-muted uppercase tracking-wider mb-1">Contacto</p>
                        <p class="text-sm text-text">{{ $location->contact_name ?? 'No especificado' }}</p>
                    </div>
                    <div class="p-4 rounded-lg bg-surface">
                        <p class="text-xs font-medium text-text-muted uppercase tracking-wider mb-1">Teléfono</p>
                        <p class="text-sm text-text">{{ $location->phone ?? 'No especificado' }}</p>
                    </div>
                </div>
            </div>

            <!-- Devices in Location -->
            <div class="bg-white rounded-xl border border-border shadow-[0_1px_3px_rgba(0,0,0,0.04)] p-6">
                <h2 class="text-lg font-semibold text-text mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    Dispositivos en esta Ubicación
                </h2>
                @if($location->devices->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="bg-surface border-b border-border">
                                    <th class="px-4 py-3 font-semibold text-text">Nombre</th>
                                    <th class="px-4 py-3 font-semibold text-text">MAC</th>
                                    <th class="px-4 py-3 font-semibold text-text">Estado</th>
                                    <th class="px-4 py-3 font-semibold text-text text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                @foreach($location->devices as $device)
                                    <tr class="hover:bg-surface/50 transition-colors">
                                        <td class="px-4 py-3 font-medium text-text">{{ $device->name }}</td>
                                        <td class="px-4 py-3 text-text-light font-mono text-xs">{{ $device->mac_address }}</td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium
                                                @if($device->status === 'active') bg-success/10 text-success
                                                @elseif($device->status === 'inactive') bg-text-muted/10 text-text-muted
                                                @elseif($device->status === 'error') bg-danger/10 text-danger
                                                @else bg-warning/10 text-warning
                                                @endif">
                                                <span class="w-1.5 h-1.5 rounded-full
                                                    @if($device->status === 'active') bg-success
                                                    @elseif($device->status === 'inactive') bg-text-muted
                                                    @elseif($device->status === 'error') bg-danger
                                                    @else bg-warning
                                                    @endif"></span>
                                                {{ $device->status === 'active' ? 'Activo' : ($device->status === 'inactive' ? 'Inactivo' : ($device->status === 'error' ? 'Error' : 'Deshabilitado')) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <a href="{{ route('devices.show', $device) }}" class="inline-flex items-center gap-1 text-sm text-primary hover:text-red-700 font-medium transition-colors">
                                                Ver
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                                </svg>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-8">
                        <div class="w-12 h-12 rounded-xl bg-surface flex items-center justify-center mx-auto mb-3">
                            <svg class="w-6 h-6 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <p class="text-sm text-text-light">No hay dispositivos en esta ubicación</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Map Card -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl border border-border shadow-[0_1px_3px_rgba(0,0,0,0.04)] p-6 sticky top-6">
                <h2 class="text-lg font-semibold text-text mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    Mapa
                </h2>
                @if($location->latitude && $location->longitude)
                    <div class="aspect-square rounded-lg bg-surface flex items-center justify-center overflow-hidden relative">
                        <iframe
                            width="100%"
                            height="100%"
                            style="border:0"
                            loading="lazy"
                            allowfullscreen
                            referrerpolicy="no-referrer-when-downgrade"
                            src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3000!2d{{ $location->longitude }}!3d{{ $location->latitude }}!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2zMzfCsDI0JzQ4LjAiTiA3wrA1MSczNi4wIg!5e0!3m2!1ses!2sco!4v1600000000000!5m2!1ses!2sco">
                        </iframe>
                    </div>
                    <div class="mt-3 text-center">
                        <a href="https://www.google.com/maps?q={{ $location->latitude }},{{ $location->longitude }}" target="_blank" class="inline-flex items-center gap-1.5 text-sm text-primary hover:text-red-700 font-medium transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                            </svg>
                            Abrir en Google Maps
                        </a>
                    </div>
                @else
                    <div class="aspect-square rounded-lg bg-surface flex flex-col items-center justify-center">
                        <svg class="w-10 h-10 text-text-muted mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        <p class="text-sm text-text-light">Coordenadas no disponibles</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Back Button -->
    <div class="mt-8">
        <a href="{{ route('locations.index') }}" class="inline-flex items-center gap-2 text-sm text-text-light hover:text-primary transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Volver a Ubicaciones
        </a>
    </div>
</div>
@endsection
