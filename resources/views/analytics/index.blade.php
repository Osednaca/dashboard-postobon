<x-app-layout>
    <x-slot name="title">Analíticas</x-slot>

    @php
        $workingHours = $kpis['working_hours'] ?? 0;
        $uptime = $kpis['uptime'] ?? 0;
        $averageRpm = $kpis['average_rpm'] ?? 0;
        $totalImpressions = $kpis['total_impressions'] ?? 0;
        $totalPlays = $kpis['total_plays'] ?? 0;
        $totalCampaigns = \App\Models\Campaign::count();
        $newCampaignsThisMonth = \App\Models\Campaign::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count();

        $activityByCity = \App\Models\Device::select('locations.city', \Illuminate\Support\Facades\DB::raw('COUNT(devices.id) as device_count'))
            ->join('locations', 'devices.location_id', '=', 'locations.id')
            ->groupBy('locations.city')
            ->get()
            ->toArray();
        $activityByGroup = \App\Models\Device::select('groups.name', \Illuminate\Support\Facades\DB::raw('COUNT(devices.id) as device_count'))
            ->join('groups', 'devices.group_id', '=', 'groups.id')
            ->groupBy('groups.name')
            ->get()
            ->toArray();
        $activityByCampaign = \App\Models\CampaignStatistic::select('campaigns.name', \Illuminate\Support\Facades\DB::raw('SUM(campaign_statistics.plays) as total_plays'))
            ->join('campaigns', 'campaign_statistics.campaign_id', '=', 'campaigns.id')
            ->groupBy('campaigns.name')
            ->get()
            ->toArray();

        $maxCity = collect($activityByCity)->max('device_count') ?: 1;
        $maxGroup = collect($activityByGroup)->max('device_count') ?: 1;
        $maxCampaignPlays = collect($activityByCampaign)->max('total_plays') ?: 1;
    @endphp

    <div class="space-y-6">
        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-text">Analíticas</h2>
                <p class="mt-1 text-sm text-text-muted">Métricas y rendimiento del sistema</p>
            </div>
            <div class="flex items-center gap-2">
                <div class="flex items-center gap-2 bg-white rounded-lg border border-border p-1">
                    <input type="date" class="px-3 py-1.5 text-sm text-text outline-none bg-transparent" value="{{ now()->subDays(30)->format('Y-m-d') }}">
                    <span class="text-text-muted">-</span>
                    <input type="date" class="px-3 py-1.5 text-sm text-text outline-none bg-transparent" value="{{ now()->format('Y-m-d') }}">
                </div>
                <button class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium text-primary bg-primary/10 hover:bg-primary/20 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                    </svg>
                    Exportar
                </button>
            </div>
        </div>

        {{-- KPI Cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white rounded-xl border border-border p-5 shadow-[0_1px_3px_rgba(0,0,0,0.08)]">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-sm text-text-muted">Horas Trabajadas</div>
                        <div class="text-2xl font-bold text-text mt-1">{{ number_format($workingHours, 0, ',', '.') }}</div>
                        <div class="text-xs text-success mt-1 flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                            </svg>
                            Total acumulado
                        </div>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-primary/10 text-primary flex items-center justify-center">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-border p-5 shadow-[0_1px_3px_rgba(0,0,0,0.08)]">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-sm text-text-muted">Uptime</div>
                        <div class="text-2xl font-bold text-text mt-1">{{ number_format($uptime, 1) }}%</div>
                        <div class="text-xs text-success mt-1 flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                            </svg>
                            Dispositivos activos
                        </div>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-success/10 text-success flex items-center justify-center">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-border p-5 shadow-[0_1px_3px_rgba(0,0,0,0.08)]">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-sm text-text-muted">RPM Promedio</div>
                        <div class="text-2xl font-bold text-text mt-1">{{ number_format($averageRpm, 0, ',', '.') }}</div>
                        <div class="text-xs text-warning mt-1 flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"></path>
                            </svg>
                            Promedio general
                        </div>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-accent/10 text-accent flex items-center justify-center">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-border p-5 shadow-[0_1px_3px_rgba(0,0,0,0.08)]">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-sm text-text-muted">Total Campañas</div>
                        <div class="text-2xl font-bold text-text mt-1">{{ number_format($totalCampaigns) }}</div>
                        <div class="text-xs text-success mt-1 flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                            </svg>
                            {{ $newCampaignsThisMonth }} nuevas este mes
                        </div>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-secondary/10 text-secondary flex items-center justify-center">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        {{-- Charts --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="bg-white rounded-xl border border-border p-6 shadow-[0_1px_3px_rgba(0,0,0,0.08)]">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-semibold text-text">Actividad por Ciudad</h3>
                    <a href="{{ route('analytics.cities') }}" class="text-xs text-primary hover:underline">Ver detalle</a>
                </div>
                <div class="space-y-3">
                    @forelse($activityByCity as $item)
                        <div class="flex items-center gap-3">
                            <span class="text-xs text-text-muted w-24">{{ $item['city'] ?? 'Sin ciudad' }}</span>
                            <div class="flex-1 h-4 bg-surface-dark rounded overflow-hidden">
                                <div class="h-full bg-primary rounded" style="width: {{ min(($item['device_count'] / $maxCity) * 100, 100) }}%"></div>
                            </div>
                            <span class="text-xs font-medium text-text w-10 text-right">{{ number_format($item['device_count']) }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-text-light text-center py-4">No hay datos por ciudad</p>
                    @endforelse
                </div>
            </div>

            <div class="bg-white rounded-xl border border-border p-6 shadow-[0_1px_3px_rgba(0,0,0,0.08)]">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-semibold text-text">Actividad por Grupo</h3>
                    <a href="{{ route('analytics.groups') }}" class="text-xs text-primary hover:underline">Ver detalle</a>
                </div>
                <div class="space-y-3">
                    @forelse($activityByGroup as $item)
                        <div class="flex items-center gap-3">
                            <span class="text-xs text-text-muted w-28">{{ $item['name'] ?? 'Sin grupo' }}</span>
                            <div class="flex-1 h-4 bg-surface-dark rounded overflow-hidden">
                                <div class="h-full bg-secondary rounded" style="width: {{ min(($item['device_count'] / $maxGroup) * 100, 100) }}%"></div>
                            </div>
                            <span class="text-xs font-medium text-text w-10 text-right">{{ number_format($item['device_count']) }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-text-light text-center py-4">No hay datos por grupo</p>
                    @endforelse
                </div>
            </div>

            <div class="bg-white rounded-xl border border-border p-6 shadow-[0_1px_3px_rgba(0,0,0,0.08)]">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-semibold text-text">Actividad por Campaña</h3>
                    <a href="{{ route('analytics.campaigns') }}" class="text-xs text-primary hover:underline">Ver detalle</a>
                </div>
                <div class="space-y-3">
                    @forelse($activityByCampaign as $item)
                        <div class="flex items-center gap-3">
                            <span class="text-xs text-text-muted w-24">{{ $item['name'] ?? 'Sin nombre' }}</span>
                            <div class="flex-1 h-4 bg-surface-dark rounded overflow-hidden">
                                <div class="h-full bg-accent rounded" style="width: {{ min(($item['total_plays'] / $maxCampaignPlays) * 100, 100) }}%"></div>
                            </div>
                            <span class="text-xs font-medium text-text w-10 text-right">{{ number_format($item['total_plays']) }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-text-light text-center py-4">No hay datos por campaña</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Quick links --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <a href="{{ route('analytics.devices') }}" class="bg-white rounded-xl border border-border p-4 hover:border-primary/50 transition-colors shadow-[0_1px_3px_rgba(0,0,0,0.08)]">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-primary/10 text-primary flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <div>
                        <div class="text-sm font-medium text-text">Dispositivos</div>
                        <div class="text-xs text-text-muted">Analíticas por dispositivo</div>
                    </div>
                </div>
            </a>
            <a href="{{ route('analytics.campaigns') }}" class="bg-white rounded-xl border border-border p-4 hover:border-primary/50 transition-colors shadow-[0_1px_3px_rgba(0,0,0,0.08)]">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-secondary/10 text-secondary flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <div>
                        <div class="text-sm font-medium text-text">Campañas</div>
                        <div class="text-xs text-text-muted">Rendimiento de campañas</div>
                    </div>
                </div>
            </a>
            <a href="{{ route('analytics.groups') }}" class="bg-white rounded-xl border border-border p-4 hover:border-primary/50 transition-colors shadow-[0_1px_3px_rgba(0,0,0,0.08)]">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-accent/10 text-accent flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                    <div>
                        <div class="text-sm font-medium text-text">Grupos</div>
                        <div class="text-xs text-text-muted">Actividad por grupo</div>
                    </div>
                </div>
            </a>
            <a href="{{ route('analytics.cities') }}" class="bg-white rounded-xl border border-border p-4 hover:border-primary/50 transition-colors shadow-[0_1px_3px_rgba(0,0,0,0.08)]">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-info/10 text-info flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                        </svg>
                    </div>
                    <div>
                        <div class="text-sm font-medium text-text">Ciudades</div>
                        <div class="text-xs text-text-muted">Visualización por ciudad</div>
                    </div>
                </div>
            </a>
        </div>
    </div>
</x-app-layout>
