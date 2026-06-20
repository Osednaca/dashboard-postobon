<x-app-layout>
    <x-slot name="title">Analíticas - Grupos</x-slot>

    <div class="space-y-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('analytics.index') }}" class="p-2 rounded-lg hover:bg-surface-dark text-text-light transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-text">Analíticas por Grupo</h2>
                <p class="mt-1 text-sm text-text-muted">Actividad y rendimiento por grupo de dispositivos</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white rounded-xl border border-border p-6 shadow-[0_1px_3px_rgba(0,0,0,0.08)]">
                <h3 class="text-sm font-semibold text-text mb-4">Dispositivos por Grupo</h3>
                <div class="space-y-3">
                    @php $groups = ['Centros Comerciales', 'Tiendas Premium', 'Zonas Turísticas', 'Eventos', 'Retail General']; @endphp
                    @foreach($groups as $group)
                        <div class="flex items-center gap-3">
                            <span class="text-xs text-text-muted w-32">{{ $group }}</span>
                            <div class="flex-1 h-4 bg-surface-dark rounded overflow-hidden">
                                <div class="h-full bg-primary rounded" style="width: {{ rand(30, 100) }}%"></div>
                            </div>
                            <span class="text-xs font-medium text-text w-10 text-right">{{ rand(5, 50) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="bg-white rounded-xl border border-border p-6 shadow-[0_1px_3px_rgba(0,0,0,0.08)]">
                <h3 class="text-sm font-semibold text-text mb-4">Actividad por Grupo</h3>
                <div class="space-y-3">
                    @foreach($groups as $group)
                        <div class="flex items-center gap-3">
                            <span class="text-xs text-text-muted w-32">{{ $group }}</span>
                            <div class="flex-1 h-4 bg-surface-dark rounded overflow-hidden">
                                <div class="h-full bg-secondary rounded" style="width: {{ rand(30, 100) }}%"></div>
                            </div>
                            <span class="text-xs font-medium text-text w-10 text-right">{{ rand(100, 5000) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-border overflow-hidden shadow-[0_1px_3px_rgba(0,0,0,0.08),0_4px_12px_rgba(0,0,0,0.05)]">
            <div class="px-6 py-4 border-b border-border">
                <h3 class="text-sm font-semibold text-text uppercase tracking-wider">Grupos</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-surface text-xs font-semibold text-text-light uppercase tracking-wider">
                        <tr>
                            <th class="px-6 py-4">Grupo</th>
                            <th class="px-6 py-4">Dispositivos</th>
                            <th class="px-6 py-4">Actividad</th>
                            <th class="px-6 py-4">Horas</th>
                            <th class="px-6 py-4">Uptime</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach($groups as $group)
                            @php
                            $devices = rand(5, 50);
                            $activity = rand(100, 5000);
                            $hours = rand(100, 2000);
                            $uptime = rand(90, 99);
                            @endphp
                            <tr class="hover:bg-surface/50 transition-colors">
                                <td class="px-6 py-4 font-medium text-text">{{ $group }}</td>
                                <td class="px-6 py-4 text-text">{{ $devices }}</td>
                                <td class="px-6 py-4 text-text">{{ number_format($activity) }}</td>
                                <td class="px-6 py-4 text-text">{{ $hours }} h</td>
                                <td class="px-6 py-4">
                                    <span class="text-sm font-medium {{ $uptime > 95 ? 'text-success' : 'text-warning' }}">{{ $uptime }}%</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
