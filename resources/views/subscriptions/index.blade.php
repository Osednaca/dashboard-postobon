<x-app-layout>
    <x-slot name="title">Suscripciones</x-slot>

    <div class="space-y-6">
        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-text">Suscripciones</h2>
                <p class="mt-1 text-sm text-text-muted">Gestiona las suscripciones del sistema</p>
            </div>
            <a href="{{ route('subscriptions.create') }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary/90 transition-colors shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Nueva Suscripción
            </a>
        </div>

        {{-- Filters --}}
        <div class="bg-white rounded-xl border border-border p-4">
            <div class="flex items-center gap-4">
                <div class="flex-1 flex items-center gap-3">
                    <svg class="w-5 h-5 text-text-muted shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                    <input type="text" placeholder="Buscar suscripciones..." class="w-full text-sm outline-none placeholder:text-text-muted">
                </div>
                <select class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text focus:border-primary focus:ring-1 focus:ring-primary outline-none">
                    <option value="">Todos los estados</option>
                    <option value="active">Activa</option>
                    <option value="suspended">Suspendida</option>
                    <option value="expired">Expirada</option>
                </select>
            </div>
        </div>

        {{-- Table --}}
        <div class="bg-white rounded-xl border border-border overflow-hidden shadow-[0_1px_3px_rgba(0,0,0,0.08),0_4px_12px_rgba(0,0,0,0.05)]">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-surface text-xs font-semibold text-text-light uppercase tracking-wider">
                        <tr>
                            <th class="px-6 py-4">Nombre</th>
                            <th class="px-6 py-4">Estado</th>
                            <th class="px-6 py-4">Fecha Inicio</th>
                            <th class="px-6 py-4">Fecha Fin</th>
                            <th class="px-6 py-4">Días Restantes</th>
                            <th class="px-6 py-4">Alerta</th>
                            <th class="px-6 py-4 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse($subscriptions ?? [] as $subscription)
                            @php
                                $remaining = isset($subscription->end_date) ? max(0, now()->diffInDays($subscription->end_date)) : rand(5, 90);
                                $alert = $remaining <= 10;
                            @endphp
                            <tr class="hover:bg-surface/50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="font-medium text-text">{{ $subscription->name ?? 'Suscripción Anual' }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    <x-status-badge :status="$subscription->status ?? 'active'" />
                                </td>
                                <td class="px-6 py-4 text-sm text-text">{{ $subscription->start_date ?? '2024-01-01' }}</td>
                                <td class="px-6 py-4 text-sm text-text">{{ $subscription->end_date ?? '2024-12-31' }}</td>
                                <td class="px-6 py-4">
                                    <span class="text-sm font-medium {{ $remaining <= 10 ? 'text-danger' : 'text-text' }}">{{ $remaining }}</span>
                                </td>
                                <td class="px-6 py-4">
                                    @if($alert)
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium bg-danger/10 text-danger border border-danger/20">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                            </svg>
                                            {{ $remaining }} días
                                        </span>
                                    @else
                                        <span class="text-xs text-text-muted">Sin alerta</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <x-action-button href="{{ route('subscriptions.renew', $subscription->id ?? 1) }}" icon="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" label="Renovar" color="success" method="POST" confirm="true" />
                                        <x-action-button href="{{ route('subscriptions.suspend', $subscription->id ?? 1) }}" icon="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z" label="Suspender" color="warning" method="POST" confirm="true" />
                                        <x-action-button href="{{ route('subscriptions.edit', $subscription->id ?? 1) }}" icon="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" label="Editar" color="primary" />
                                        <x-action-button href="{{ route('subscriptions.destroy', $subscription->id ?? 1) }}" icon="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" label="Eliminar" color="danger" method="DELETE" confirm="true" />
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center gap-3">
                                        <div class="w-12 h-12 rounded-full bg-surface-dark flex items-center justify-center">
                                            <svg class="w-6 h-6 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                        </div>
                                        <p class="text-text-muted">No hay suscripciones registradas</p>
                                        <a href="{{ route('subscriptions.create') }}" class="text-primary text-sm font-medium hover:underline">Crear primera suscripción</a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if(isset($subscriptions) && $subscriptions->hasPages())
                <div class="border-t border-border">
                    <x-pagination :paginator="$subscriptions" />
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
