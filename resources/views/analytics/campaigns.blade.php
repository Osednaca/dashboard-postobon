<x-app-layout>
    <x-slot name="title">Analíticas - Campañas</x-slot>

    <div class="space-y-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('analytics.index') }}" class="p-2 rounded-lg hover:bg-surface-dark text-text-light transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-text">Analíticas por Campaña</h2>
                <p class="mt-1 text-sm text-text-muted">Rendimiento de las campañas publicitarias</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="bg-white rounded-xl border border-border p-6 shadow-[0_1px_3px_rgba(0,0,0,0.08)]">
                <h3 class="text-sm font-semibold text-text mb-4">Impresiones</h3>
                <div class="space-y-3">
                    @php $campaigns = ['Verano 2024', 'Navidad', 'Lanzamiento', 'Promo Flash', 'Evento Especial']; @endphp
                    @foreach($campaigns as $campaign)
                        <div class="flex items-center gap-3">
                            <span class="text-xs text-text-muted w-24">{{ $campaign }}</span>
                            <div class="flex-1 h-4 bg-surface-dark rounded overflow-hidden">
                                <div class="h-full bg-primary rounded" style="width: {{ rand(20, 100) }}%"></div>
                            </div>
                            <span class="text-xs font-medium text-text w-14 text-right">{{ number_format(rand(1000, 50000)) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="bg-white rounded-xl border border-border p-6 shadow-[0_1px_3px_rgba(0,0,0,0.08)]">
                <h3 class="text-sm font-semibold text-text mb-4">Reproducciones</h3>
                <div class="space-y-3">
                    @foreach($campaigns as $campaign)
                        <div class="flex items-center gap-3">
                            <span class="text-xs text-text-muted w-24">{{ $campaign }}</span>
                            <div class="flex-1 h-4 bg-surface-dark rounded overflow-hidden">
                                <div class="h-full bg-secondary rounded" style="width: {{ rand(20, 100) }}%"></div>
                            </div>
                            <span class="text-xs font-medium text-text w-14 text-right">{{ number_format(rand(500, 25000)) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="bg-white rounded-xl border border-border p-6 shadow-[0_1px_3px_rgba(0,0,0,0.08)]">
                <h3 class="text-sm font-semibold text-text mb-4">Duración Total (min)</h3>
                <div class="space-y-3">
                    @foreach($campaigns as $campaign)
                        <div class="flex items-center gap-3">
                            <span class="text-xs text-text-muted w-24">{{ $campaign }}</span>
                            <div class="flex-1 h-4 bg-surface-dark rounded overflow-hidden">
                                <div class="h-full bg-accent rounded" style="width: {{ rand(20, 100) }}%"></div>
                            </div>
                            <span class="text-xs font-medium text-text w-14 text-right">{{ number_format(rand(100, 5000)) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-border overflow-hidden shadow-[0_1px_3px_rgba(0,0,0,0.08),0_4px_12px_rgba(0,0,0,0.05)]">
            <div class="px-6 py-4 border-b border-border">
                <h3 class="text-sm font-semibold text-text uppercase tracking-wider">Rendimiento de Campañas</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-surface text-xs font-semibold text-text-light uppercase tracking-wider">
                        <tr>
                            <th class="px-6 py-4">Campaña</th>
                            <th class="px-6 py-4">Estado</th>
                            <th class="px-6 py-4">Impresiones</th>
                            <th class="px-6 py-4">Reproducciones</th>
                            <th class="px-6 py-4">Duración</th>
                            <th class="px-6 py-4">Eficiencia</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach($campaigns as $campaign)
                            @php
                            $impressions = rand(1000, 50000);
                            $plays = rand(500, 25000);
                            $efficiency = $impressions > 0 ? round(($plays / $impressions) * 100, 1) : 0;
                            @endphp
                            <tr class="hover:bg-surface/50 transition-colors">
                                <td class="px-6 py-4 font-medium text-text">{{ $campaign }}</td>
                                <td class="px-6 py-4">
                                    <x-status-badge :status="['active', 'finished', 'active', 'paused', 'active'][array_rand([0,1,2,3,4])]" />
                                </td>
                                <td class="px-6 py-4 text-text">{{ number_format($impressions) }}</td>
                                <td class="px-6 py-4 text-text">{{ number_format($plays) }}</td>
                                <td class="px-6 py-4 text-text">{{ number_format(rand(100, 5000)) }} min</td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <div class="w-16 h-1.5 bg-surface-dark rounded-full overflow-hidden">
                                            <div class="h-full rounded-full {{ $efficiency > 50 ? 'bg-success' : ($efficiency > 30 ? 'bg-warning' : 'bg-danger') }}" style="width: {{ $efficiency }}%"></div>
                                        </div>
                                        <span class="text-xs font-medium text-text">{{ $efficiency }}%</span>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
