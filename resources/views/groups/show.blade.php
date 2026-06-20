@extends('layouts.app')

@section('title', $group->name)

@section('content')
<div class="max-w-5xl mx-auto" x-data="{ showCampaignModal: false }">
    <!-- Page Header -->
    <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-sm text-text-light mb-2">
                <a href="{{ route('groups.index') }}" class="hover:text-primary transition-colors">Grupos</a>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
                <span class="text-text">{{ $group->name }}</span>
            </div>
            <h1 class="text-2xl font-bold text-text">{{ $group->name }}</h1>
            @if($group->description)
                <p class="text-sm text-text-light mt-1">{{ $group->description }}</p>
            @endif
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <form action="{{ route('groups.power-on', $group) }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-success/10 text-success text-sm font-medium hover:bg-success/20 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    Encender Grupo
                </button>
            </form>
            <form action="{{ route('groups.power-off', $group) }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-warning/10 text-warning text-sm font-medium hover:bg-warning/20 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                    </svg>
                    Apagar Grupo
                </button>
            </form>
            <button @click="showCampaignModal = true" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-primary/10 text-primary text-sm font-medium hover:bg-primary/20 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/>
                </svg>
                Publicar Campaña
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Group Details -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl border border-border shadow-[0_1px_3px_rgba(0,0,0,0.04)] p-6 sticky top-6">
                <h2 class="text-lg font-semibold text-text mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Detalles del Grupo
                </h2>
                <div class="space-y-4">
                    <div class="p-4 rounded-lg bg-surface">
                        <p class="text-xs font-medium text-text-muted uppercase tracking-wider mb-1">Nombre</p>
                        <p class="text-sm text-text font-medium">{{ $group->name }}</p>
                    </div>
                    <div class="p-4 rounded-lg bg-surface">
                        <p class="text-xs font-medium text-text-muted uppercase tracking-wider mb-1">Descripción</p>
                        <p class="text-sm text-text">{{ $group->description ?? 'Sin descripción' }}</p>
                    </div>
                    <div class="p-4 rounded-lg bg-surface">
                        <p class="text-xs font-medium text-text-muted uppercase tracking-wider mb-1">Dispositivos</p>
                        <p class="text-sm text-text">{{ $group->devices->count() }} dispositivo{{ $group->devices->count() !== 1 ? 's' : '' }}</p>
                    </div>
                    <div class="p-4 rounded-lg bg-surface">
                        <p class="text-xs font-medium text-text-muted uppercase tracking-wider mb-1">Dispositivos Activos</p>
                        <p class="text-sm text-text">{{ $group->devices->where('status', 'active')->count() }} activo{{ $group->devices->where('status', 'active')->count() !== 1 ? 's' : '' }}</p>
                    </div>
                </div>

                <div class="mt-6 pt-6 border-t border-border">
                    <a href="{{ route('groups.edit', $group) }}" class="inline-flex items-center justify-center w-full gap-2 px-4 py-2.5 bg-primary text-white rounded-lg font-medium text-sm hover:bg-red-700 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        Editar Grupo
                    </a>
                </div>
            </div>
        </div>

        <!-- Devices in Group -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl border border-border shadow-[0_1px_3px_rgba(0,0,0,0.04)] p-6">
                <h2 class="text-lg font-semibold text-text mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    Dispositivos en este Grupo
                </h2>
                @if($group->devices->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="bg-surface border-b border-border">
                                    <th class="px-4 py-3 font-semibold text-text">Nombre</th>
                                    <th class="px-4 py-3 font-semibold text-text">MAC</th>
                                    <th class="px-4 py-3 font-semibold text-text">Estado</th>
                                    <th class="px-4 py-3 font-semibold text-text">Ubicación</th>
                                    <th class="px-4 py-3 font-semibold text-text text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                @foreach($group->devices as $device)
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
                                        <td class="px-4 py-3 text-text-light">{{ $device->location->name ?? '-' }}</td>
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
                        <p class="text-sm text-text-light">No hay dispositivos en este grupo</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Campaign Assignment Modal -->
    <div x-show="showCampaignModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display: none;">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showCampaignModal = false"></div>
        <div class="relative bg-white rounded-xl shadow-xl border border-border p-6 w-full max-w-md" @click.away="showCampaignModal = false">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center">
                    <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-text">Publicar Campaña</h3>
                </div>
            </div>
            <p class="text-sm text-text-light mb-4">Selecciona una campaña para publicar en todos los dispositivos del grupo <span class="font-medium text-text">{{ $group->name }}</span>.</p>
            <form action="{{ route('groups.publish-campaign', $group) }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label for="campaign_id" class="block text-sm font-medium text-text mb-2">Campaña</label>
                    <select id="campaign_id" name="campaign_id" required class="w-full px-4 py-2.5 rounded-lg border border-border text-sm text-text focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all bg-white">
                        <option value="">Seleccionar campaña</option>
                        @foreach(App\Models\Campaign::all() as $campaign)
                            <option value="{{ $campaign->id }}">{{ $campaign->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" @click="showCampaignModal = false" class="px-4 py-2.5 rounded-lg border border-border text-sm font-medium text-text hover:bg-surface transition-colors">Cancelar</button>
                    <button type="submit" class="px-4 py-2.5 rounded-lg bg-primary text-white text-sm font-medium hover:bg-red-700 transition-colors">Publicar</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
