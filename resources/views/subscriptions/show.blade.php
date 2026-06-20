<x-app-layout>
    <x-slot name="title">Detalle de Suscripción</x-slot>

    @php
    $subscription = $subscription ?? (object)[
        'id' => 1,
        'name' => 'Suscripción Anual 2024',
        'status' => 'active',
        'start_date' => '2024-01-01',
        'end_date' => '2024-12-31',
        'alert_days' => 7,
        'restrictions' => 'Máximo 10 dispositivos activos. Soporte incluido.',
        'created_at' => '2023-12-15',
        'devices_used' => 7,
        'devices_limit' => 10,
    ];
    $remaining = max(0, now()->diffInDays($subscription->end_date));
    $total = now()->diffInDays($subscription->start_date) + $remaining;
    $progress = $total > 0 ? (($total - $remaining) / $total) * 100 : 100;
    @endphp

    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('subscriptions.index') }}" class="p-2 rounded-lg hover:bg-surface-dark text-text-light transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                </a>
                <div>
                    <div class="flex items-center gap-3">
                        <h2 class="text-2xl font-bold text-text">{{ $subscription->name }}</h2>
                        <x-status-badge :status="$subscription->status" />
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('subscriptions.edit', $subscription->id) }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium text-primary bg-primary/10 hover:bg-primary/20 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                    Editar
                </a>
                <form method="POST" action="{{ route('subscriptions.renew', $subscription->id) }}" class="inline">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium text-success bg-success/10 hover:bg-success/20 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        Renovar
                    </button>
                </form>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Details Card --}}
            <div class="bg-white rounded-xl border border-border p-6 shadow-[0_1px_3px_rgba(0,0,0,0.08)]">
                <h3 class="text-sm font-semibold text-text uppercase tracking-wider mb-4">Información</h3>
                <div class="space-y-4">
                    <div class="flex justify-between py-2 border-b border-border">
                        <span class="text-sm text-text-muted">Fecha inicio</span>
                        <span class="text-sm font-medium text-text">{{ $subscription->start_date }}</span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-border">
                        <span class="text-sm text-text-muted">Fecha fin</span>
                        <span class="text-sm font-medium text-text">{{ $subscription->end_date }}</span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-border">
                        <span class="text-sm text-text-muted">Días restantes</span>
                        <span class="text-sm font-medium {{ $remaining <= 10 ? 'text-danger' : 'text-text' }}">{{ $remaining }}</span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-border">
                        <span class="text-sm text-text-muted">Días de alerta</span>
                        <span class="text-sm font-medium text-text">{{ $subscription->alert_days }}</span>
                    </div>
                    <div class="flex justify-between py-2 border-b border-border">
                        <span class="text-sm text-text-muted">Dispositivos</span>
                        <span class="text-sm font-medium text-text">{{ $subscription->devices_used }} / {{ $subscription->devices_limit }}</span>
                    </div>
                    <div class="py-2">
                        <span class="text-sm text-text-muted">Restricciones</span>
                        <p class="text-sm text-text mt-1">{{ $subscription->restrictions }}</p>
                    </div>
                </div>
            </div>

            {{-- Countdown & Usage --}}
            <div class="lg:col-span-2 space-y-6">
                {{-- Countdown --}}
                <div class="bg-white rounded-xl border border-border p-6 shadow-[0_1px_3px_rgba(0,0,0,0.08)]" x-data="{ days: {{ $remaining }}, hours: 12, minutes: 34, seconds: 56 }" x-init="setInterval(() => { if(seconds > 0) { seconds-- } else { seconds = 59; if(minutes > 0) { minutes-- } else { minutes = 59; if(hours > 0) { hours-- } else { hours = 23; if(days > 0) { days-- } } } } }, 1000)">
                    <h3 class="text-sm font-semibold text-text uppercase tracking-wider mb-4">Expiración</h3>
                    <div class="grid grid-cols-4 gap-4">
                        <div class="text-center p-4 bg-surface rounded-lg">
                            <div class="text-2xl font-bold text-text" x-text="String(days).padStart(2, '0')">{{ str_pad($remaining, 2, '0', STR_PAD_LEFT) }}</div>
                            <div class="text-xs text-text-muted mt-1">Días</div>
                        </div>
                        <div class="text-center p-4 bg-surface rounded-lg">
                            <div class="text-2xl font-bold text-text" x-text="String(hours).padStart(2, '0')">00</div>
                            <div class="text-xs text-text-muted mt-1">Horas</div>
                        </div>
                        <div class="text-center p-4 bg-surface rounded-lg">
                            <div class="text-2xl font-bold text-text" x-text="String(minutes).padStart(2, '0')">00</div>
                            <div class="text-xs text-text-muted mt-1">Minutos</div>
                        </div>
                        <div class="text-center p-4 bg-surface rounded-lg">
                            <div class="text-2xl font-bold text-text" x-text="String(seconds).padStart(2, '0')">00</div>
                            <div class="text-xs text-text-muted mt-1">Segundos</div>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="flex justify-between text-xs mb-1.5">
                            <span class="text-text-muted">Progreso de suscripción</span>
                            <span class="text-text font-medium">{{ round($progress, 1) }}%</span>
                        </div>
                        <div class="w-full h-2 rounded-full bg-surface-dark overflow-hidden">
                            <div class="h-full rounded-full transition-all {{ $progress > 90 ? 'bg-danger' : 'bg-primary' }}" style="width: {{ $progress }}%"></div>
                        </div>
                    </div>
                </div>

                {{-- Usage Metrics --}}
                <div class="bg-white rounded-xl border border-border p-6 shadow-[0_1px_3px_rgba(0,0,0,0.08)]">
                    <h3 class="text-sm font-semibold text-text uppercase tracking-wider mb-4">Uso</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="p-4 bg-surface rounded-lg">
                            <div class="text-sm text-text-muted">Dispositivos activos</div>
                            <div class="text-xl font-bold text-text mt-1">{{ $subscription->devices_used }}</div>
                            <div class="text-xs text-text-muted mt-1">de {{ $subscription->devices_limit }} permitidos</div>
                        </div>
                        <div class="p-4 bg-surface rounded-lg">
                            <div class="text-sm text-text-muted">Campañas activas</div>
                            <div class="text-xl font-bold text-text mt-1">3</div>
                            <div class="text-xs text-text-muted mt-1">en esta suscripción</div>
                        </div>
                        <div class="p-4 bg-surface rounded-lg">
                            <div class="text-sm text-text-muted">Horas totales</div>
                            <div class="text-xl font-bold text-text mt-1">1,248</div>
                            <div class="text-xs text-text-muted mt-1">acumuladas este mes</div>
                        </div>
                    </div>
                </div>

                {{-- Alert History --}}
                <div class="bg-white rounded-xl border border-border overflow-hidden shadow-[0_1px_3px_rgba(0,0,0,0.08)]">
                    <div class="px-6 py-4 border-b border-border">
                        <h3 class="text-sm font-semibold text-text uppercase tracking-wider">Historial de alertas</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="bg-surface text-xs font-semibold text-text-light uppercase tracking-wider">
                                <tr>
                                    <th class="px-6 py-3">Fecha</th>
                                    <th class="px-6 py-3">Tipo</th>
                                    <th class="px-6 py-3">Mensaje</th>
                                    <th class="px-6 py-3">Estado</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                @php $alerts = [
                                    ['date' => '2024-06-01', 'type' => 'info', 'message' => 'Suscripción activada correctamente', 'read' => true],
                                    ['date' => '2024-05-25', 'type' => 'warning', 'message' => 'La suscripción expira en 7 días', 'read' => false],
                                ]; @endphp
                                @foreach($alerts as $alert)
                                    <tr class="hover:bg-surface/50 transition-colors">
                                        <td class="px-6 py-3 text-text">{{ $alert['date'] }}</td>
                                        <td class="px-6 py-3">
                                            <span class="inline-flex items-center gap-1.5 text-xs font-medium {{ $alert['type'] === 'warning' ? 'text-warning' : 'text-info' }}">
                                                <span class="w-1.5 h-1.5 rounded-full {{ $alert['type'] === 'warning' ? 'bg-warning' : 'bg-info' }}"></span>
                                                {{ $alert['type'] === 'warning' ? 'Advertencia' : 'Info' }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-3 text-text">{{ $alert['message'] }}</td>
                                        <td class="px-6 py-3">
                                            <span class="text-xs {{ $alert['read'] ? 'text-text-muted' : 'text-primary font-medium' }}">{{ $alert['read'] ? 'Leído' : 'No leído' }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
