@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    @push('styles')
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    @endpush
    @push('scripts')
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    @endpush

    @php
        $kpis = $data['kpis'] ?? [];
        $campaignStatuses = $data['campaign_statuses'] ?? [];
        $mapData = $data['map_data'] ?? [];
        $unknownDevices = $kpis['unknown_devices'] ?? 0;
        $unmappedEstablishments = $data['unmapped_establishments'] ?? 0;
    @endphp

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
        <section class="bg-white rounded-xl border border-border p-5 shadow-sm" aria-label="Resumen de dispositivos">
            <h2 class="text-sm font-medium text-text-muted mb-3">Total Dispositivos</h2>
            <div class="flex flex-wrap items-baseline gap-2">
                <span class="text-2xl font-bold text-text" data-dashboard-total>{{ number_format($kpis['total_devices'] ?? 0) }}</span>
                <span class="text-xs font-medium text-success">{{ number_format($kpis['online_devices'] ?? 0) }} en línea</span>
                <span class="text-xs text-text-muted">/ {{ number_format($kpis['offline_devices'] ?? 0) }} sin conexión</span>
                @if($unknownDevices)
                    <span class="text-xs text-amber-700">/ {{ number_format($unknownDevices) }} sin confirmar</span>
                @endif
            </div>
            <p class="mt-3 text-xs text-text-muted">Dispositivos guardados y detectados. Conexión consultada al abrir esta página.</p>
        </section>
        <section class="bg-white rounded-xl border border-border p-5 shadow-sm" aria-label="Resumen de campañas">
            <h2 class="text-sm font-medium text-text-muted mb-3">Campañas Activas</h2>
            <div class="flex flex-wrap items-baseline gap-2">
                <span class="text-2xl font-bold text-text">{{ number_format($campaignStatuses['active'] ?? 0) }}</span>
                <span class="text-xs font-medium text-warning">{{ number_format($campaignStatuses['scheduled'] ?? 0) }} pendientes</span>
            </div>
        </section>
    </div>

    @if($unknownDevices)
        <p role="status" class="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            No se pudo confirmar la conexión de {{ number_format($unknownDevices) }} dispositivo(s). Conservamos su registro; no se cuentan como desconectados.
        </p>
    @endif

    <section class="bg-white rounded-xl border border-border p-5 shadow-sm">
        <h2 class="text-sm font-semibold text-text mb-2">Ubicación de Establecimientos</h2>
        <p class="text-sm text-text-light mb-4">Selecciona un establecimiento para ver sus dispositivos asignados.</p>
        <div class="flex flex-wrap gap-4 mb-4 text-xs text-text-light" aria-label="Leyenda del mapa">
            <span><span class="inline-block w-2 h-2 rounded-full bg-success mr-1"></span>Con dispositivos en línea</span>
            <span><span class="inline-block w-2 h-2 rounded-full bg-slate-500 mr-1"></span>Sin dispositivos en línea</span>
            <span><span class="inline-block w-2 h-2 rounded-full bg-amber-500 mr-1"></span>Conexión sin confirmar</span>
        </div>
        <div id="device-map" class="relative isolate z-0 rounded-lg border border-border h-[420px] w-full" aria-label="Mapa de establecimientos"></div>
        <p id="dashboard-map-message" class="mt-3 text-sm text-text-muted" role="status" @if(count($mapData)) hidden @endif>No hay establecimientos con coordenadas registradas.</p>
        @if($unmappedEstablishments)
            <p class="mt-3 text-sm text-text-muted">{{ number_format($unmappedEstablishments) }} establecimiento(s) sin coordenadas válidas no aparecen en el mapa.</p>
        @endif
        <script id="dashboard-map-data" type="application/json">{!! \Illuminate\Support\Js::encode($mapData) !!}</script>
    </section>
@endsection
