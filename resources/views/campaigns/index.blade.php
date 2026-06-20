<x-app-layout>
    <x-slot name="title">Campañas</x-slot>

    <div class="space-y-6">
        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-text">Campañas</h2>
                <p class="mt-1 text-sm text-text-muted">Gestiona las campañas publicitarias del sistema</p>
            </div>
            <a href="{{ route('campaigns.create') }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary/90 transition-colors shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Nueva Campaña
            </a>
        </div>

        {{-- Filters --}}
        <div class="bg-white rounded-xl border border-border p-4" x-data="{ filterOpen: false }">
            <div class="flex items-center gap-4">
                <div class="flex-1 flex items-center gap-3">
                    <svg class="w-5 h-5 text-text-muted shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                    <input type="text" placeholder="Buscar campañas..." class="w-full text-sm outline-none placeholder:text-text-muted" value="{{ request('search') }}">
                </div>
                <button @click="filterOpen = !filterOpen" class="flex items-center gap-2 px-3 py-2 rounded-lg border border-border text-sm font-medium text-text-light hover:bg-surface-dark transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                    </svg>
                    Filtros
                </button>
            </div>

            <div x-show="filterOpen" x-transition class="mt-4 pt-4 border-t border-border grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-medium text-text-light mb-1.5">Estado</label>
                    <select name="status" class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text focus:border-primary focus:ring-1 focus:ring-primary outline-none">
                        <option value="">Todos</option>
                        <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>Borrador</option>
                        <option value="scheduled" {{ request('status') == 'scheduled' ? 'selected' : '' }}>Programada</option>
                        <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Activa</option>
                        <option value="paused" {{ request('status') == 'paused' ? 'selected' : '' }}>Pausada</option>
                        <option value="finished" {{ request('status') == 'finished' ? 'selected' : '' }}>Finalizada</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-text-light mb-1.5">Prioridad</label>
                    <select name="priority" class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text focus:border-primary focus:ring-1 focus:ring-primary outline-none">
                        <option value="">Todas</option>
                        <option value="high">Alta (8-10)</option>
                        <option value="medium">Media (4-7)</option>
                        <option value="low">Baja (1-3)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-text-light mb-1.5">Fecha inicio</label>
                    <input type="date" name="start_date" class="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text focus:border-primary focus:ring-1 focus:ring-primary outline-none" value="{{ request('start_date') }}">
                </div>
                <div class="flex items-end">
                    <button type="button" class="w-full px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary/90 transition-colors">Aplicar</button>
                </div>
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
                            <th class="px-6 py-4">Prioridad</th>
                            <th class="px-6 py-4">Fechas</th>
                            <th class="px-6 py-4">Videos</th>
                            <th class="px-6 py-4">Segmentación</th>
                            <th class="px-6 py-4 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse($campaigns as $campaign)
                            <tr class="hover:bg-surface/50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="font-medium text-text">{{ $campaign->name }}</div>
                                    <div class="text-xs text-text-muted mt-0.5">{{ Str::limit($campaign->description, 60) }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    <x-campaign-status-badge :status="$campaign->status" />
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-1">
                                        <span class="text-sm font-semibold text-text">{{ $campaign->priority }}</span>
                                        <div class="w-16 h-1.5 rounded-full bg-surface-dark overflow-hidden">
                                            <div class="h-full rounded-full bg-primary" style="width: {{ $campaign->priority * 10 }}%"></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-text">{{ $campaign->start_date ? $campaign->start_date->format('Y-m-d') : '-' }}</div>
                                    <div class="text-xs text-text-muted">{{ $campaign->end_date ? $campaign->end_date->format('Y-m-d') : '-' }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-1.5 text-sm text-text-light">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                        </svg>
                                        {{ $campaign->media()->count() }}
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-wrap gap-1">
                                        @foreach($campaign->segment_cities ?? [] as $city)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-surface-dark text-text-light">{{ $city }}</span>
                                        @endforeach
                                        @if(empty($campaign->segment_cities))
                                            <span class="text-xs text-text-muted">Sin segmentación</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <x-action-button href="{{ route('campaigns.edit', $campaign) }}" icon="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" label="Editar" color="primary" />
                                        <x-action-button href="{{ route('campaigns.show', $campaign) }}" icon="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" label="Ver" />

                                        @if($campaign->status == 'draft')
                                            <x-action-button href="{{ route('campaigns.activate', $campaign) }}" icon="M13 10V3L4 14h7v7l9-11h-7z" label="Activar" color="success" method="POST" confirm="true" />
                                        @elseif($campaign->status == 'active')
                                            <x-action-button href="{{ route('campaigns.pause', $campaign) }}" icon="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z" label="Pausar" color="warning" method="POST" confirm="true" />
                                            <x-action-button href="{{ route('campaigns.finish', $campaign) }}" icon="M5 13l4 4L19 7" label="Finalizar" color="info" method="POST" confirm="true" />
                                        @elseif($campaign->status == 'paused')
                                            <x-action-button href="{{ route('campaigns.activate', $campaign) }}" icon="M13 10V3L4 14h7v7l9-11h-7z" label="Reactivar" color="success" method="POST" confirm="true" />
                                        @endif

                                        <x-action-button href="{{ route('campaigns.destroy', $campaign) }}" icon="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" label="Eliminar" color="danger" method="DELETE" confirm="true" />
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center gap-3">
                                        <div class="w-12 h-12 rounded-full bg-surface-dark flex items-center justify-center">
                                            <svg class="w-6 h-6 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                            </svg>
                                        </div>
                                        <p class="text-text-muted">No hay campañas registradas</p>
                                        <a href="{{ route('campaigns.create') }}" class="text-primary text-sm font-medium hover:underline">Crear primera campaña</a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if($campaigns->hasPages())
                <div class="border-t border-border">
                    <x-pagination :paginator="$campaigns" />
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
