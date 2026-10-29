@extends('layouts.app')

@section('title', 'Editar Grupo')

@section('content')
<div class="max-w-5xl mx-auto">
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center gap-2 text-sm text-text-light mb-2">
            <a href="{{ route('groups.index') }}" class="hover:text-primary transition-colors">Grupos</a>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
            <span class="text-text">Editar Grupo</span>
        </div>
        <h1 class="text-2xl font-bold text-text">Editar Grupo</h1>
        <p class="text-sm text-text-light mt-1">Actualiza la información de {{ $group->name }}</p>
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

        <form action="{{ route('groups.update', $group) }}" method="POST" class="space-y-6">
            @csrf
            @method('PUT')

            <!-- Name -->
            <div>
                <label for="name" class="block text-sm font-medium text-text mb-2">Nombre <span class="text-danger">*</span></label>
                <input type="text" id="name" name="name" value="{{ old('name', $group->name) }}" required
                       class="w-full px-4 py-2.5 rounded-lg border border-border text-sm text-text placeholder:text-text-muted focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all">
            </div>

            <!-- Description -->
            <div>
                <label for="description" class="block text-sm font-medium text-text mb-2">Descripción</label>
                <textarea id="description" name="description" rows="4"
                          class="w-full px-4 py-2.5 rounded-lg border border-border text-sm text-text placeholder:text-text-muted focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all resize-none">{{ old('description', $group->description) }}</textarea>
            </div>

            <section class="space-y-3 border-t border-border pt-6">
                <h2 class="text-lg font-semibold text-text">Dispositivos actuales ({{ count($currentDevices) }})</h2>
                <p class="text-sm text-text-light">Marca los equipos que quieras quitar del grupo al guardar.</p>
                <ul class="divide-y divide-border rounded-lg border border-border">
                    @forelse($currentDevices as $device)
                        <li class="flex items-center justify-between gap-3 p-3">
                            <label class="flex flex-1 cursor-pointer items-center gap-3">
                                <input type="checkbox" name="remove_device_keys[]" value="{{ $device['key'] }}" @checked(in_array($device['key'], (array) old('remove_device_keys', []), true)) class="rounded border-border text-primary">
                                <span><span class="block font-medium text-text">{{ $device['name'] }}</span><span class="text-xs text-text-muted">{{ $device['establishment_name'] ?? 'Sin establecimiento' }} · Quitar del grupo</span></span>
                            </label>
                            <span class="rounded-full bg-surface px-2 py-1 text-xs font-medium">{{ $device['type'] === 'wl35' ? '35' : '42' }}</span>
                        </li>
                    @empty
                        <li class="p-4 text-sm text-text-muted">El grupo todavía no tiene dispositivos.</li>
                    @endforelse
                </ul>
            </section>

            <section class="space-y-4 border-t border-border pt-6" x-data="groupDeviceSelector({ devices: @js($groupDevices), selected: @js(old('device_keys', [])) })">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div><h2 class="text-lg font-semibold text-text">Añadir dispositivos</h2><p class="mt-1 text-sm text-text-light">Selecciona más equipos 42 y 35 para este grupo.</p></div>
                    <span class="rounded-full bg-primary/10 px-3 py-1 text-sm font-medium text-primary" x-text="selected.length + ' por añadir'" aria-live="polite"></span>
                </div>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div><label for="device-search" class="mb-1 block text-xs font-medium text-text-light">Buscar equipo o establecimiento</label><input id="device-search" x-model="search" type="search" placeholder="Nombre, establecimiento o grupo" class="w-full rounded-lg border border-border px-3 py-2 text-sm"></div>
                    <div><label for="device-model" class="mb-1 block text-xs font-medium text-text-light">Modelo</label><select id="device-model" x-model="model" class="w-full rounded-lg border border-border bg-white px-3 py-2 text-sm"><option value="">Todos</option><option value="z2">42</option><option value="wl35">35</option></select></div>
                    <div><label for="device-business-type" class="mb-1 block text-xs font-medium text-text-light">Tipo de negocio del establecimiento</label><select id="device-business-type" x-model="businessType" class="w-full rounded-lg border border-border bg-white px-3 py-2 text-sm"><option value="">Todos</option>@foreach($businessTypes as $businessType)<option value="{{ $businessType->id }}">{{ $businessType->name }}</option>@endforeach</select></div>
                </div>
                <div class="flex flex-wrap gap-4 text-sm font-medium"><button type="button" @click="selectVisible()" class="text-primary hover:underline">Seleccionar resultados</button><button type="button" @click="clearSelection()" class="text-text-light hover:underline">Quitar selección</button></div>
                <div x-show="movedDevices.length" x-cloak class="rounded-lg border border-warning/30 bg-warning/10 p-3 text-sm text-amber-900" role="status">
                    <p><strong x-text="movedDevices.length"></strong> equipo(s) ya pertenecen a otro grupo. Al guardar se moverán a {{ $group->name }}.</p>
                    <ul class="mt-2 list-inside list-disc"><template x-for="device in movedDevices" :key="device.key"><li><span x-text="device.name"></span> — <span x-text="device.group_name || 'Otro grupo'"></span></li></template></ul>
                </div>
                <div class="max-h-96 overflow-y-auto rounded-lg border border-border divide-y divide-border">
                    <template x-for="device in filteredDevices" :key="device.key">
                        <label class="flex cursor-pointer items-start gap-3 p-4 hover:bg-surface">
                            <input type="checkbox" :value="device.key" x-model="selected" class="mt-1 rounded border-border text-primary">
                            <span class="min-w-0 flex-1"><span class="block font-medium text-text" x-text="device.name"></span><span class="block text-xs text-text-light" x-text="[device.establishment_name || 'Sin establecimiento', device.business_type_name, device.group_name ? 'Grupo actual: ' + device.group_name : 'Sin grupo'].filter(Boolean).join(' · ')"></span><span class="block break-all text-xs text-text-muted" x-text="device.id"></span></span>
                            <span class="rounded-full bg-surface px-2 py-1 text-xs font-medium" x-text="device.type === 'wl35' ? '35' : '42'"></span>
                        </label>
                    </template>
                    <p x-show="!filteredDevices.length" class="p-6 text-center text-sm text-text-muted">No hay más dispositivos que coincidan con la búsqueda.</p>
                </div>
                <template x-for="key in selected" :key="key"><input type="hidden" name="device_keys[]" :value="key"></template>
                <noscript><p class="text-sm text-danger">Activa JavaScript para seleccionar dispositivos.</p></noscript>
            </section>

            <!-- Actions -->
            <div class="flex items-center justify-end gap-3 pt-6 border-t border-border">
                <a href="{{ route('groups.index') }}" class="px-4 py-2.5 rounded-lg border border-border text-sm font-medium text-text hover:bg-surface transition-colors">Cancelar</a>
                <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 bg-primary text-white rounded-lg font-medium text-sm hover:bg-primary/90 transition-colors shadow-sm shadow-primary/20">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Actualizar Grupo
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
