@extends('layouts.app')

@section('title', 'Editar Dispositivo')

@section('content')
<div class="max-w-3xl mx-auto">
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center gap-2 text-sm text-text-light mb-2">
            <a href="{{ route('devices.index') }}" class="hover:text-primary transition-colors">Dispositivos</a>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
            <span class="text-text">Editar Dispositivo</span>
        </div>
        <h1 class="text-2xl font-bold text-text">Editar Dispositivo</h1>
        <p class="text-sm text-text-light mt-1">Actualiza la información de {{ $device->name }}</p>
    </div>

    <!-- Form -->
    <div class="bg-white rounded-xl border border-border shadow-[0_1px_3px_rgba(0,0,0,0.04)] p-6 sm:p-8">
        @if ($errors->any())
            <div class="mb-6 p-4 rounded-lg bg-danger/10 border border-danger/20 text-danger text-sm">
                <div class="flex items-center gap-2 mb-2 font-semibold">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Por favor corrige los siguientes errores:
                </div>
                <ul class="list-disc list-inside space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('devices.update', $device) }}" method="POST" class="space-y-6">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <!-- Name -->
                <div class="sm:col-span-2">
                    <label for="name" class="block text-sm font-medium text-text mb-2">Nombre <span class="text-danger">*</span></label>
                    <input type="text" id="name" name="name" value="{{ old('name', $device->name) }}" required
                           class="w-full px-4 py-2.5 rounded-lg border border-border text-sm text-text placeholder:text-text-muted focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all">
                </div>

                <!-- MAC Address -->
                <div>
                    <label for="mac_address" class="block text-sm font-medium text-text mb-2">Dirección MAC <span class="text-danger">*</span></label>
                    <input type="text" id="mac_address" name="mac_address" value="{{ old('mac_address', $device->mac_address) }}" required
                           class="w-full px-4 py-2.5 rounded-lg border border-border text-sm text-text placeholder:text-text-muted focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all font-mono">
                </div>

                <!-- RPM -->
                <div>
                    <label for="rpm" class="block text-sm font-medium text-text mb-2">RPM</label>
                    <input type="number" id="rpm" name="rpm" value="{{ old('rpm', $device->rpm) }}"
                           class="w-full px-4 py-2.5 rounded-lg border border-border text-sm text-text placeholder:text-text-muted focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all">
                </div>

                <!-- Firmware -->
                <div>
                    <label for="firmware" class="block text-sm font-medium text-text mb-2">Firmware</label>
                    <input type="text" id="firmware" name="firmware" value="{{ old('firmware', $device->firmware) }}"
                           class="w-full px-4 py-2.5 rounded-lg border border-border text-sm text-text placeholder:text-text-muted focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all">
                </div>

                <!-- Hardware -->
                <div>
                    <label for="hardware" class="block text-sm font-medium text-text mb-2">Hardware</label>
                    <input type="text" id="hardware" name="hardware" value="{{ old('hardware', $device->hardware) }}"
                           class="w-full px-4 py-2.5 rounded-lg border border-border text-sm text-text placeholder:text-text-muted focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all">
                </div>

                <!-- Location -->
                <div>
                    <label for="location_id" class="block text-sm font-medium text-text mb-2">Ubicación</label>
                    <select id="location_id" name="location_id"
                            class="w-full px-4 py-2.5 rounded-lg border border-border text-sm text-text focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all bg-white">
                        <option value="">Seleccionar ubicación</option>
                        @foreach(App\Models\Location::all() as $location)
                            <option value="{{ $location->id }}" {{ old('location_id', $device->location_id) == $location->id ? 'selected' : '' }}>{{ $location->name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Group -->
                <div>
                    <label for="group_id" class="block text-sm font-medium text-text mb-2">Grupo</label>
                    <select id="group_id" name="group_id"
                            class="w-full px-4 py-2.5 rounded-lg border border-border text-sm text-text focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all bg-white">
                        <option value="">Seleccionar grupo</option>
                        @foreach(App\Models\Group::all() as $group)
                            <option value="{{ $group->id }}" {{ old('group_id', $device->group_id) == $group->id ? 'selected' : '' }}>{{ $group->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- Additional Info -->
            <div class="p-4 rounded-lg bg-surface border border-border">
                <h3 class="text-sm font-semibold text-text mb-3">Información del Sistema</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="text-text-muted">Último Heartbeat:</span>
                        <span class="text-text ml-1">{{ $device->last_heartbeat_at ? $device->last_heartbeat_at->format('d/m/Y H:i') : 'Nunca' }}</span>
                    </div>
                    <div>
                        <span class="text-text-muted">Horas de Trabajo:</span>
                        <span class="text-text ml-1">{{ $device->working_hours ? number_format($device->working_hours, 1) . 'h' : '-' }}</span>
                    </div>
                    <div>
                        <span class="text-text-muted">Estado de Energía:</span>
                        <span class="text-text ml-1 capitalize">{{ $device->power_status ?? 'Desconocido' }}</span>
                    </div>
                    <div>
                        <span class="text-text-muted">Estado General:</span>
                        <span class="text-text ml-1 capitalize">{{ $device->status ?? 'Desconocido' }}</span>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center justify-end gap-3 pt-6 border-t border-border">
                <a href="{{ route('devices.index') }}" class="px-4 py-2.5 rounded-lg border border-border text-sm font-medium text-text hover:bg-surface transition-colors">Cancelar</a>
                <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 bg-primary text-white rounded-lg font-medium text-sm hover:bg-red-700 transition-colors shadow-[0_1px_3px_rgba(0,0,0,0.08)]">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Actualizar Dispositivo
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
