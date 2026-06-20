<x-app-layout>
    <x-slot name="title">Analíticas - Dispositivos</x-slot>

    <div class="space-y-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('analytics.index') }}" class="p-2 rounded-lg hover:bg-surface-dark text-text-light transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-text">Analíticas por Dispositivo</h2>
                <p class="mt-1 text-sm text-text-muted">Métricas detalladas de cada dispositivo</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white rounded-xl border border-border p-6 shadow-[0_1px_3px_rgba(0,0,0,0.08)]">
                <h3 class="text-sm font-semibold text-text mb-4">Uptime por Dispositivo</h3>
                <div class="space-y-3">
                    @for($i = 1; $i <= 8; $i++)
                        @php $uptime = rand(85, 99.9); @endphp
                        <div class="flex items-center gap-3">
                            <span class="text-xs text-text-muted w-20">Fan {{ str_pad($i, 3, '0', STR_PAD_LEFT) }}</span>
                            <div class="flex-1 h-4 bg-surface-dark rounded overflow-hidden">
                                <div class="h-full rounded {{ $uptime > 95 ? 'bg-success' : ($uptime > 90 ? 'bg-warning' : 'bg-danger') }}" style="width: {{ $uptime }}%"></div>
                            </div>
                            <span class="text-xs font-medium text-text w-12 text-right">{{ number_format($uptime, 1) }}%</span>
                        </div>
                    @endfor
                </div>
            </div>

            <div class="bg-white rounded-xl border border-border p-6 shadow-[0_1px_3px_rgba(0,0,0,0.08)]">
                <h3 class="text-sm font-semibold text-text mb-4">Horas de Trabajo (últimos 7 días)</h3>
                <div class="space-y-3">
                    @for($i = 1; $i <= 8; $i++)
                        @php $hours = rand(20, 80); @endphp
                        <div class="flex items-center gap-3">
                            <span class="text-xs text-text-muted w-20">Fan {{ str_pad($i, 3, '0', STR_PAD_LEFT) }}</span>
                            <div class="flex-1 h-4 bg-surface-dark rounded overflow-hidden">
                                <div class="h-full bg-primary rounded" style="width: {{ ($hours / 80) * 100 }}%"></div>
                            </div>
                            <span class="text-xs font-medium text-text w-12 text-right">{{ $hours }}h</span>
                        </div>
                    @endfor
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-border overflow-hidden shadow-[0_1px_3px_rgba(0,0,0,0.08),0_4px_12px_rgba(0,0,0,0.05)]">
            <div class="px-6 py-4 border-b border-border">
                <h3 class="text-sm font-semibold text-text uppercase tracking-wider">Dispositivos</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-surface text-xs font-semibold text-text-light uppercase tracking-wider">
                        <tr>
                            <th class="px-6 py-4">Dispositivo</th>
                            <th class="px-6 py-4">Ubicación</th>
                            <th class="px-6 py-4">Horas</th>
                            <th class="px-6 py-4">Uptime</th>
                            <th class="px-6 py-4">Reproducciones</th>
                            <th class="px-6 py-4">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @for($i = 1; $i <= 10; $i++)
                            <tr class="hover:bg-surface/50 transition-colors">
                                <td class="px-6 py-4 font-medium text-text">Fan 3D {{ str_pad($i, 3, '0', STR_PAD_LEFT) }}</td>
                                <td class="px-6 py-4 text-text-light">Bogotá, CC Andino</td>
                                <td class="px-6 py-4 text-text">{{ rand(20, 80) }} h</td>
                                <td class="px-6 py-4">
                                    @php $u = rand(85, 99); @endphp
                                    <span class="text-sm font-medium {{ $u > 95 ? 'text-success' : ($u > 90 ? 'text-warning' : 'text-danger') }}">{{ $u }}%</span>
                                </td>
                                <td class="px-6 py-4 text-text">{{ number_format(rand(500, 5000)) }}</td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded text-xs bg-success/10 text-success border border-success/20">
                                        <span class="w-1.5 h-1.5 rounded-full bg-success"></span>
                                        En línea
                                    </span>
                                </td>
                            </tr>
                        @endfor
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
