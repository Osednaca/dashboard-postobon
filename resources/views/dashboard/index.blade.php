@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')

    @push('styles')
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
        <style>
            #device-map { height: 100%; min-height: 400px; }
        </style>
    @endpush

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    @endpush

    @php
        $kpis = $data['kpis'] ?? [];
        $deviceStatuses = $data['device_statuses'] ?? [];
        $campaignStatuses = $data['campaign_statuses'] ?? [];
        $mapData = $data['map_data'] ?? [];
        $recentCampaigns = $data['recent_campaigns'] ?? [];
        $activityByCity = $data['activity_by_city'] ?? [];
        $activityByGroup = $data['activity_by_group'] ?? [];
        $activityByCampaign = $data['activity_by_campaign'] ?? [];
        $auditLogs = \App\Models\AuditLog::with('user')->latest()->limit(10)->get();

        $totalDevices = $kpis['total_devices'] ?? 0;
        $onlineDevices = $kpis['online_devices'] ?? 0;
        $offlineDevices = $kpis['offline_devices'] ?? 0;
        $totalWorkingHours = $kpis['total_working_hours'] ?? 0;
        $uptimePercentage = $kpis['uptime_percentage'] ?? 0;
        $totalCampaigns = array_sum($campaignStatuses);
        $activeCampaigns = $campaignStatuses['active'] ?? 0;
        $pendingCampaigns = $campaignStatuses['scheduled'] ?? 0;
    @endphp

    {{-- KPI Row --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl border border-border p-5 shadow-sm">
            <div class="flex items-center justify-between mb-3">
                <span class="text-sm font-medium text-text-muted">Total Dispositivos</span>
                <div class="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center">
                    <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                    </svg>
                </div>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl font-bold text-text">{{ number_format($totalDevices) }}</span>
                <span class="text-xs font-medium text-success">{{ number_format($onlineDevices) }} en línea</span>
                <span class="text-xs text-text-muted">/ {{ number_format($offlineDevices) }} offline</span>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-border p-5 shadow-sm">
            <div class="flex items-center justify-between mb-3">
                <span class="text-sm font-medium text-text-muted">Campañas Activas</span>
                <div class="w-8 h-8 rounded-lg bg-secondary/10 flex items-center justify-center">
                    <svg class="w-4 h-4 text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"></path>
                    </svg>
                </div>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl font-bold text-text">{{ number_format($activeCampaigns) }}</span>
                <span class="text-xs font-medium text-warning">{{ number_format($pendingCampaigns) }} pendientes</span>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-border p-5 shadow-sm">
            <div class="flex items-center justify-between mb-3">
                <span class="text-sm font-medium text-text-muted">Horas de Trabajo</span>
                <div class="w-8 h-8 rounded-lg bg-accent/10 flex items-center justify-center">
                    <svg class="w-4 h-4 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl font-bold text-text">{{ number_format($totalWorkingHours, 0, ',', '.') }}</span>
                <span class="text-xs text-text-muted">total acumulado</span>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-border p-5 shadow-sm">
            <div class="flex items-center justify-between mb-3">
                <span class="text-sm font-medium text-text-muted">Tiempo Activo</span>
                <div class="w-8 h-8 rounded-lg bg-success/10 flex items-center justify-center">
                    <svg class="w-4 h-4 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                </div>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl font-bold text-text">{{ number_format($uptimePercentage, 1) }}%</span>
                <span class="text-xs text-success">{{ $uptimePercentage >= 95 ? 'Excelente' : 'Revisar' }}</span>
            </div>
        </div>
    </div>

    {{-- Charts Row --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
        <div class="bg-white rounded-xl border border-border p-5 shadow-sm">
            <h3 class="text-sm font-semibold text-text mb-4">Actividad por Grupo</h3>
            <div class="h-64">
                <canvas id="deviceActivityChart"></canvas>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-border p-5 shadow-sm">
            <h3 class="text-sm font-semibold text-text mb-4">Rendimiento de Campañas</h3>
            <div class="h-64">
                <canvas id="campaignChart"></canvas>
            </div>
        </div>
    </div>

    {{-- Map Card --}}
    <div class="bg-white rounded-xl border border-border p-5 shadow-sm mb-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-text">Ubicación de Dispositivos</h3>
            <div class="flex items-center gap-3 text-xs">
                <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-success"></span> En línea</span>
                <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-warning"></span> Pausado</span>
                <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-danger"></span> Offline</span>
            </div>
        </div>
        <div id="device-map" class="rounded-lg border border-border"></div>
    </div>

    {{-- Status Cards Row --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
        <div class="bg-white rounded-xl border border-border p-5 shadow-sm">
            <h3 class="text-sm font-semibold text-text mb-4">Estado de Dispositivos</h3>
            <div class="h-56 flex items-center justify-center">
                <canvas id="deviceStatusChart"></canvas>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-border p-5 shadow-sm">
            <h3 class="text-sm font-semibold text-text mb-4">Estado de Campañas</h3>
            <div class="h-56 flex items-center justify-center">
                <canvas id="campaignStatusChart"></canvas>
            </div>
        </div>
    </div>

    {{-- Recent Activity Table --}}
    <div class="bg-white rounded-xl border border-border shadow-sm mb-6">
        <div class="px-5 py-4 border-b border-border flex items-center justify-between">
            <h3 class="text-sm font-semibold text-text">Actividad Reciente</h3>
            <a href="{{ route('audit.index') }}" class="text-xs font-medium text-primary hover:text-primary/80 transition-colors">Ver todo</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-surface text-xs font-semibold text-text-muted uppercase">
                    <tr>
                        <th class="px-5 py-3">Usuario</th>
                        <th class="px-5 py-3">Acción</th>
                        <th class="px-5 py-3">Entidad</th>
                        <th class="px-5 py-3">Hora</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse($auditLogs as $log)
                        <tr class="hover:bg-surface/50 transition-colors">
                            <td class="px-5 py-3 font-medium text-text">{{ $log->user?->name ?? 'Sistema' }}</td>
                            <td class="px-5 py-3 text-text-light">{{ $log->action }}</td>
                            <td class="px-5 py-3 text-text-light">{{ $log->entity_type }} #{{ $log->entity_id }}</td>
                            <td class="px-5 py-3 text-text-muted text-xs">{{ $log->created_at->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-5 py-8 text-center text-sm text-text-light">No hay actividad reciente</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Quick Actions Row --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <a href="{{ route('devices.create') }}" class="flex flex-col items-center gap-3 p-5 bg-white rounded-xl border border-border shadow-sm hover:border-primary/30 hover:shadow-md transition-all group">
            <div class="w-10 h-10 rounded-lg bg-primary/10 flex items-center justify-center group-hover:bg-primary/20 transition-colors">
                <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
            </div>
            <span class="text-sm font-medium text-text group-hover:text-primary transition-colors">Agregar Dispositivo</span>
        </a>

        <a href="{{ route('campaigns.create') }}" class="flex flex-col items-center gap-3 p-5 bg-white rounded-xl border border-border shadow-sm hover:border-secondary/30 hover:shadow-md transition-all group">
            <div class="w-10 h-10 rounded-lg bg-secondary/10 flex items-center justify-center group-hover:bg-secondary/20 transition-colors">
                <svg class="w-5 h-5 text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"></path>
                </svg>
            </div>
            <span class="text-sm font-medium text-text group-hover:text-secondary transition-colors">Crear Campaña</span>
        </a>

        <a href="{{ route('media.create') }}" class="flex flex-col items-center gap-3 p-5 bg-white rounded-xl border border-border shadow-sm hover:border-accent/30 hover:shadow-md transition-all group">
            <div class="w-10 h-10 rounded-lg bg-accent/10 flex items-center justify-center group-hover:bg-accent/20 transition-colors">
                <svg class="w-5 h-5 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
            </div>
            <span class="text-sm font-medium text-text group-hover:text-accent transition-colors">Subir Medio</span>
        </a>

        <a href="{{ route('schedules.create') }}" class="flex flex-col items-center gap-3 p-5 bg-white rounded-xl border border-border shadow-sm hover:border-info/30 hover:shadow-md transition-all group">
            <div class="w-10 h-10 rounded-lg bg-info/10 flex items-center justify-center group-hover:bg-info/20 transition-colors">
                <svg class="w-5 h-5 text-info" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
            </div>
            <span class="text-sm font-medium text-text group-hover:text-info transition-colors">Agregar Horario</span>
        </a>
    </div>

    {{-- Chart.js & Leaflet Initialization --}}
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Device Activity by Group Bar Chart
            const ctxActivity = document.getElementById('deviceActivityChart');
            if (ctxActivity) {
                new Chart(ctxActivity, {
                    type: 'bar',
                    data: {
                        labels: {!! json_encode(collect($activityByGroup)->pluck('group')->toArray()) !!},
                        datasets: [{
                            label: 'Dispositivos',
                            data: {!! json_encode(collect($activityByGroup)->pluck('device_count')->toArray()) !!},
                            backgroundColor: '#FF0000',
                            borderRadius: 4,
                            barPercentage: 0.6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false }
                        },
                        scales: {
                            y: { beginAtZero: true, grid: { color: '#F0F0F0' } },
                            x: { grid: { display: false } }
                        }
                    }
                });
            }

            // Campaign Performance Bar Chart
            const ctxCampaign = document.getElementById('campaignChart');
            if (ctxCampaign) {
                new Chart(ctxCampaign, {
                    type: 'bar',
                    data: {
                        labels: {!! json_encode(collect($activityByCampaign)->pluck('campaign')->toArray()) !!},
                        datasets: [{
                            label: 'Reproducciones',
                            data: {!! json_encode(collect($activityByCampaign)->pluck('total_plays')->toArray()) !!},
                            backgroundColor: '#F57CB6',
                            borderRadius: 4,
                            barPercentage: 0.6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false }
                        },
                        scales: {
                            y: { beginAtZero: true, grid: { color: '#F0F0F0' } },
                            x: { grid: { display: false } }
                        }
                    }
                });
            }

            // Device Status Pie Chart
            const ctxDeviceStatus = document.getElementById('deviceStatusChart');
            if (ctxDeviceStatus) {
                const deviceLabels = {!! json_encode(array_keys($deviceStatuses)) !!};
                const deviceCounts = {!! json_encode(array_values($deviceStatuses)) !!};
                const labelMap = {
                    'active': 'En línea',
                    'inactive': 'Offline',
                    'error': 'Error',
                    'disabled': 'Deshabilitado'
                };
                const colorMap = {
                    'active': '#10B981',
                    'inactive': '#EF4444',
                    'error': '#F59E0B',
                    'disabled': '#3B82F6'
                };
                new Chart(ctxDeviceStatus, {
                    type: 'doughnut',
                    data: {
                        labels: deviceLabels.map(l => labelMap[l] || l),
                        datasets: [{
                            data: deviceCounts,
                            backgroundColor: deviceLabels.map(l => colorMap[l] || '#999'),
                            borderWidth: 0,
                            hoverOffset: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '70%',
                        plugins: {
                            legend: { position: 'bottom', labels: { usePointStyle: true, padding: 15 } }
                        }
                    }
                });
            }

            // Campaign Status Pie Chart
            const ctxCampaignStatus = document.getElementById('campaignStatusChart');
            if (ctxCampaignStatus) {
                const campaignLabels = {!! json_encode(array_keys($campaignStatuses)) !!};
                const campaignCounts = {!! json_encode(array_values($campaignStatuses)) !!};
                const labelMap = {
                    'draft': 'Borrador',
                    'scheduled': 'Programada',
                    'active': 'Activa',
                    'paused': 'Pausada',
                    'finished': 'Finalizada'
                };
                const colorMap = {
                    'draft': '#666666',
                    'scheduled': '#3B82F6',
                    'active': '#FF0000',
                    'paused': '#F59E0B',
                    'finished': '#10B981'
                };
                new Chart(ctxCampaignStatus, {
                    type: 'doughnut',
                    data: {
                        labels: campaignLabels.map(l => labelMap[l] || l),
                        datasets: [{
                            data: campaignCounts,
                            backgroundColor: campaignLabels.map(l => colorMap[l] || '#999'),
                            borderWidth: 0,
                            hoverOffset: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '70%',
                        plugins: {
                            legend: { position: 'bottom', labels: { usePointStyle: true, padding: 15 } }
                        }
                    }
                });
            }

            // Leaflet Map
            const mapContainer = document.getElementById('device-map');
            if (mapContainer && typeof L !== 'undefined') {
                const mapData = {!! json_encode($mapData) !!};
                const hasValidCoords = mapData.some(d => d.latitude && d.longitude);
                const centerLat = hasValidCoords ? mapData.find(d => d.latitude && d.longitude)?.latitude : 4.7110;
                const centerLng = hasValidCoords ? mapData.find(d => d.latitude && d.longitude)?.longitude : -74.0721;

                const map = L.map('device-map').setView([centerLat, centerLng], hasValidCoords ? 6 : 5);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenStreetMap contributors',
                    maxZoom: 18
                }).addTo(map);

                const statusColors = {
                    active: '#10B981',
                    paused: '#F59E0B',
                    inactive: '#EF4444',
                    error: '#EF4444',
                    disabled: '#3B82F6'
                };
                const statusLabels = {
                    active: 'En línea',
                    paused: 'Pausado',
                    inactive: 'Offline',
                    error: 'Error',
                    disabled: 'Deshabilitado'
                };

                mapData.forEach(device => {
                    if (device.latitude && device.longitude) {
                        L.circleMarker([device.latitude, device.longitude], {
                            radius: 8,
                            fillColor: statusColors[device.status] || '#999',
                            color: '#FFFFFF',
                            weight: 2,
                            opacity: 1,
                            fillOpacity: 0.9
                        }).addTo(map)
                        .bindPopup(`<b>${device.device_name}</b><br>Ciudad: ${device.city || '-'}<br>Dirección: ${device.address || '-'}<br>Estado: ${statusLabels[device.status] || device.status}`);
                    }
                });
            }
        });
    </script>

@endsection
