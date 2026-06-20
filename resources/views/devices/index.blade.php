@extends('layouts.app')

@section('title', 'Dispositivos')

@section('content')
<div x-data="{ 
    statusFilter: '', 
    locationFilter: '', 
    groupFilter: '', 
    search: '', 
    showDeleteModal: false, 
    deleteId: null, 
    deleteName: '',
    selectedIds: [],
    showBulkMenu: false,
    toggleAll() {
        const checkboxes = document.querySelectorAll('input[name=&quot;device_ids[]&quot;]');
        if (this.selectedIds.length === checkboxes.length) {
            this.selectedIds = [];
        } else {
            this.selectedIds = Array.from(checkboxes).map(cb => cb.value);
        }
    }
}">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl font-bold text-text">Dispositivos</h1>
            <p class="text-sm text-text-light mt-1">Gestiona y monitorea todos tus dispositivos 3D Fan</p>
        </div>
        <a href="{{ route('devices.create') }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-primary text-white rounded-lg font-medium text-sm hover:bg-red-700 transition-colors shadow-[0_1px_3px_rgba(0,0,0,0.08)]">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Nuevo Dispositivo
        </a>
    </div>

    <!-- Filters & Bulk Actions -->
    <div class="bg-white rounded-xl border border-border p-4 mb-6 shadow-[0_1px_3px_rgba(0,0,0,0.04)]">
        <div class="flex flex-col lg:flex-row gap-3">
            <div class="flex-1 relative">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="text" x-model="search" placeholder="Buscar por nombre o MAC..." class="w-full pl-10 pr-4 py-2.5 rounded-lg border border-border text-sm text-text placeholder:text-text-muted focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all">
            </div>
            <div class="sm:w-40">
                <select x-model="statusFilter" class="w-full px-4 py-2.5 rounded-lg border border-border text-sm text-text focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all bg-white">
                    <option value="">Todos los estados</option>
                    <option value="active">Activo</option>
                    <option value="inactive">Inactivo</option>
                    <option value="error">Error</option>
                    <option value="disabled">Deshabilitado</option>
                </select>
            </div>
            <div class="sm:w-48">
                <select x-model="locationFilter" class="w-full px-4 py-2.5 rounded-lg border border-border text-sm text-text focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all bg-white">
                    <option value="">Todas las ubicaciones</option>
                    @foreach(App\Models\Location::all() as $location)
                        <option value="{{ $location->id }}">{{ $location->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:w-48">
                <select x-model="groupFilter" class="w-full px-4 py-2.5 rounded-lg border border-border text-sm text-text focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all bg-white">
                    <option value="">Todos los grupos</option>
                    @foreach(App\Models\Group::all() as $group)
                        <option value="{{ $group->id }}">{{ $group->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <!-- Bulk Actions Bar -->
        <div x-show="selectedIds.length > 0" x-transition class="mt-4 pt-4 border-t border-border flex flex-wrap items-center gap-3">
            <span class="text-sm text-text-light font-medium" x-text="selectedIds.length + ' seleccionado(s)'"></span>
            <div class="relative" x-data="{ open: false }">
                <button @click="open = !open" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-border text-sm font-medium text-text hover:bg-surface transition-colors">
                    Acciones en lote
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="open" @click.away="open = false" class="absolute left-0 mt-2 w-56 bg-white rounded-lg shadow-lg border border-border py-1 z-50" style="display: none;">
                    <button type="button" class="block w-full text-left px-4 py-2 text-sm text-text hover:bg-surface">Encender</button>
                    <button type="button" class="block w-full text-left px-4 py-2 text-sm text-text hover:bg-surface">Apagar</button>
                    <button type="button" class="block w-full text-left px-4 py-2 text-sm text-text hover:bg-surface">Deshabilitar</button>
                    <button type="button" class="block w-full text-left px-4 py-2 text-sm text-text hover:bg-surface">Habilitar</button>
                    <div class="border-t border-border my-1"></div>
                    <button type="button" class="block w-full text-left px-4 py-2 text-sm text-text hover:bg-surface">Cambiar Grupo</button>
                    <button type="button" class="block w-full text-left px-4 py-2 text-sm text-text hover:bg-surface">Cambiar Ubicación</button>
                    <div class="border-t border-border my-1"></div>
                    <button type="button" class="block w-full text-left px-4 py-2 text-sm text-danger hover:bg-surface">Desvincular</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl border border-border shadow-[0_1px_3px_rgba(0,0,0,0.04)] overflow-hidden">
        @if($devices->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="bg-surface border-b border-border">
                            <th class="px-4 py-4">
                                <input type="checkbox" @click="toggleAll()" class="rounded border-border text-primary focus:ring-primary/20">
                            </th>
                            <th class="px-4 py-4 font-semibold text-text">Nombre</th>
                            <th class="px-4 py-4 font-semibold text-text">MAC</th>
                            <th class="px-4 py-4 font-semibold text-text">Estado</th>
                            <th class="px-4 py-4 font-semibold text-text">Ubicación</th>
                            <th class="px-4 py-4 font-semibold text-text">Grupo</th>
                            <th class="px-4 py-4 font-semibold text-text">Último Heartbeat</th>
                            <th class="px-4 py-4 font-semibold text-text">RPM</th>
                            <th class="px-4 py-4 font-semibold text-text">Horas</th>
                            <th class="px-4 py-4 font-semibold text-text text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach($devices as $device)
                            <tr class="hover:bg-surface/50 transition-colors"
                                x-show="
                                    (!search || '{{ strtolower($device->name) }}'.includes(search.toLowerCase()) || '{{ strtolower($device->mac_address) }}'.includes(search.toLowerCase())) &&
                                    (!statusFilter || '{{ $device->status }}' === statusFilter) &&
                                    (!locationFilter || '{{ $device->location_id }}' === locationFilter) &&
                                    (!groupFilter || '{{ $device->group_id }}' === groupFilter)
                                ">
                                <td class="px-4 py-4">
                                    <input type="checkbox" name="device_ids[]" value="{{ $device->id }}" x-model="selectedIds" class="rounded border-border text-primary focus:ring-primary/20">
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center">
                                            <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                            </svg>
                                        </div>
                                        <span class="font-medium text-text">{{ $device->name }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-text-light font-mono text-xs">{{ $device->mac_address }}</td>
                                <td class="px-4 py-4">
                                    @php
                                        $indicatorStatus = match($device->status) {
                                            'active' => 'online',
                                            'inactive' => 'offline',
                                            'error' => 'offline',
                                            'disabled' => 'disabled',
                                            default => 'offline',
                                        };
                                    @endphp
                                    <x-device-status-indicator :status="$indicatorStatus" />
                                </td>
                                <td class="px-4 py-4 text-text-light">{{ $device->location?->name ?? '-' }}</td>
                                <td class="px-4 py-4 text-text-light">{{ $device->group?->name ?? '-' }}</td>
                                <td class="px-4 py-4 text-text-light">{{ $device->last_heartbeat_at ? $device->last_heartbeat_at->diffForHumans() : 'Nunca' }}</td>
                                <td class="px-4 py-4 text-text-light font-mono">{{ $device->rpm ?? '-' }}</td>
                                <td class="px-4 py-4 text-text-light">{{ $device->working_hours ? number_format($device->working_hours, 1) . 'h' : '-' }}</td>
                                <td class="px-4 py-4">
                                    <div class="flex items-center justify-end gap-1">
                                        <a href="{{ route('devices.show', $device) }}" class="p-1.5 rounded-lg text-text-light hover:text-info hover:bg-info/10 transition-colors" title="Ver Detalles">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                        </a>
                                        <form action="{{ route('devices.power-on', $device) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit" class="p-1.5 rounded-lg text-text-light hover:text-success hover:bg-success/10 transition-colors" title="Encender">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                                </svg>
                                            </button>
                                        </form>
                                        <form action="{{ route('devices.power-off', $device) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit" class="p-1.5 rounded-lg text-text-light hover:text-warning hover:bg-warning/10 transition-colors" title="Apagar">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                                                </svg>
                                            </button>
                                        </form>
                                        <a href="{{ route('devices.edit', $device) }}" class="p-1.5 rounded-lg text-text-light hover:text-primary hover:bg-primary/10 transition-colors" title="Editar">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                        </a>
                                        <button @click="showDeleteModal = true; deleteId = {{ $device->id }}; deleteName = '{{ $device->name }}'" class="p-1.5 rounded-lg text-text-light hover:text-danger hover:bg-danger/10 transition-colors" title="Eliminar">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($devices->hasPages())
                <div class="px-6 py-4 border-t border-border">
                    {{ $devices->links() }}
                </div>
            @endif
        @else
            <div class="flex flex-col items-center justify-center py-16 px-4">
                <div class="w-16 h-16 rounded-2xl bg-surface flex items-center justify-center mb-4">
                    <svg class="w-8 h-8 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-text mb-1">No hay dispositivos</h3>
                <p class="text-sm text-text-light mb-4">Aún no has registrado ningún dispositivo.</p>
                <a href="{{ route('devices.create') }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-primary text-white rounded-lg font-medium text-sm hover:bg-red-700 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Registrar dispositivo
                </a>
            </div>
        @endif
    </div>

    <!-- Delete Confirmation Modal -->
    <div x-show="showDeleteModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display: none;">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showDeleteModal = false"></div>
        <div class="relative bg-white rounded-xl shadow-xl border border-border p-6 w-full max-w-md" @click.away="showDeleteModal = false">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-danger/10 flex items-center justify-center">
                    <svg class="w-5 h-5 text-danger" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-text">¿Eliminar dispositivo?</h3>
                </div>
            </div>
            <p class="text-sm text-text-light mb-6">Estás a punto de eliminar <span class="font-medium text-text" x-text="deleteName"></span>. Esta acción no se puede deshacer.</p>
            <div class="flex justify-end gap-3">
                <button @click="showDeleteModal = false" class="px-4 py-2.5 rounded-lg border border-border text-sm font-medium text-text hover:bg-surface transition-colors">Cancelar</button>
                <form :action="'/devices/' + deleteId" method="POST" class="inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="px-4 py-2.5 rounded-lg bg-danger text-white text-sm font-medium hover:bg-red-700 transition-colors">Eliminar</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
