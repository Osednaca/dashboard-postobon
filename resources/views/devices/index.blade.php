@extends('layouts.app')

@section('title', 'Dispositivos')

@section('content')
@php
    $initialLocalIds = collect(old('device_ids', []))->map(fn ($id) => (string) $id)->unique()->values();
    $localFleetKeys = $devices->getCollection()->mapWithKeys(function ($device) {
        $mac = strtoupper(str_replace(':', '', (string) $device->mac_address));
        return $mac === '' ? [] : [(string) $device->id => 'z2:'.$mac];
    });
    $initialFleetKeys = collect(old('targets', []))
        ->merge($initialLocalIds->map(fn ($id) => $localFleetKeys->get($id))->filter())
        ->map(fn ($key) => (string) $key)->unique()->values();
@endphp

<div x-data="{
    statusFilter: '', protocolFilter: '', locationFilter: '', search: '',
    showDeleteModal: false, showBulkFormatSdModal: false, showBulkAssignMediaModal: false,
    showFleetPlayModal: false, showFleetUploadModal: false,
    bulkAssigningMedia: false, fleetSubmitting: false,
    deleteId: null, deleteName: '',
    selectedIds: {{ Js::from($initialLocalIds) }},
    selectedFleetKeys: {{ Js::from($initialFleetKeys) }},
    bulkGroupId: '', bulkLocationId: '',
    toggleDevice(key, localId, checked) {
        this.selectedFleetKeys = checked
            ? [...new Set([...this.selectedFleetKeys, key])]
            : this.selectedFleetKeys.filter(value => value !== key);
        if (localId !== null) {
            const id = String(localId);
            this.selectedIds = checked
                ? [...new Set([...this.selectedIds, id])]
                : this.selectedIds.filter(value => value !== id);
        }
    },
    toggleAll() {
        const selectors = [...document.querySelectorAll('input[data-fleet-selector]')]
            .filter(input => input.offsetParent !== null);
        const allSelected = selectors.length > 0 && selectors.every(input => this.selectedFleetKeys.includes(input.value));
        if (allSelected) {
            const keys = selectors.map(input => input.value);
            const ids = selectors.map(input => input.dataset.localId).filter(Boolean);
            this.selectedFleetKeys = this.selectedFleetKeys.filter(key => !keys.includes(key));
            this.selectedIds = this.selectedIds.filter(id => !ids.includes(id));
            return;
        }
        selectors.forEach(input => this.toggleDevice(input.value, input.dataset.localId || null, true));
    },
    submitBulk(action) {
        if (!this.selectedIds.length) return;
        document.getElementById('bulk-operation-action').value = action;
        document.getElementById('bulk-operation-group').value = this.bulkGroupId;
        document.getElementById('bulk-operation-location').value = this.bulkLocationId;
        document.getElementById('bulk-operation-form').submit();
    },
    submitFleet(command, value) {
        if (!this.selectedFleetKeys.length || this.fleetSubmitting) return;
        const form = this.$refs.fleetCommand;
        form.querySelector('[name=command]').value = command;
        form.querySelector('[name=value]').value = value;
        this.fleetSubmitting = true;
        form.submit();
    }
}" class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <div class="mb-2 flex flex-wrap items-center gap-2">
                <span class="rounded-md bg-amber-100 px-2 py-1 text-[10px] font-bold uppercase tracking-widest text-amber-700">Z2 {{ $devices->total() }}</span>
                <span class="rounded-md bg-sky-100 px-2 py-1 text-[10px] font-bold uppercase tracking-widest text-sky-700">WL35 {{ $wl35Devices->count() }}</span>
            </div>
            <h1 class="text-2xl font-bold text-text">Todos los dispositivos</h1>
            <p class="mt-1 text-sm text-text-light">Administra Z2 y WL35 desde una sola lista y una misma selección.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('devices.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-border bg-white px-4 py-2.5 text-sm font-medium text-text-light transition hover:bg-surface-dark">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Actualizar
            </a>
            <a href="{{ route('devices.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-white shadow-sm shadow-primary/20 transition hover:bg-primary/90">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Nuevo Z2
            </a>
        </div>
    </div>

    @if($gatewayError)
        <div class="flex items-start gap-3 rounded-xl border border-warning/30 bg-warning/10 px-4 py-3 text-sm text-amber-900">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-warning" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <div>
                <div class="font-semibold">Los Z2 siguen disponibles, pero no fue posible cargar los WL35.</div>
                <div class="mt-1 text-xs leading-5 text-amber-800">{{ $gatewayError }}</div>
            </div>
        </div>
    @elseif(!$gatewayConfigured)
        <div class="rounded-xl border border-warning/30 bg-warning/10 px-4 py-3 text-sm text-amber-900">
            Los WL35 ya se pueden consultar, pero las órdenes unificadas requieren configurar <code class="font-semibold">UNIFIED_FLEET_API_TOKEN</code> con el mismo valor de <code class="font-semibold">ADMIN_TOKEN</code> del gateway.
        </div>
    @endif

    @if(session('fleet_results'))
        <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
            @foreach(session('fleet_results') as $result)
                <div class="flex items-start gap-3 rounded-xl border px-4 py-3 {{ ($result['success'] ?? false) ? 'border-success/20 bg-success/5' : 'border-danger/20 bg-danger/5' }}">
                    <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ ($result['success'] ?? false) ? 'bg-success' : 'bg-danger' }}"></span>
                    <div class="min-w-0"><div class="truncate text-xs font-semibold text-text">{{ $result['key'] ?? $result['id'] ?? 'Equipo' }}</div><div class="mt-0.5 text-xs text-text-light">{{ ($result['success'] ?? false) ? 'Orden aceptada' : ($result['error'] ?? 'La operación falló') }}</div></div>
                </div>
            @endforeach
        </div>
    @endif

    <section class="rounded-xl border border-border bg-white p-4 shadow-[0_1px_3px_rgba(0,0,0,0.04)]">
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
            <label class="relative xl:col-span-2">
                <svg class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="search" x-model="search" placeholder="Buscar nombre, MAC o identificador…" class="w-full rounded-lg border border-border py-2.5 pl-10 pr-4 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20">
            </label>
            <select x-model="protocolFilter" class="rounded-lg border border-border bg-white px-3 py-2.5 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                <option value="">Todos los tipos</option><option value="z2">Z2</option><option value="wl35">WL35</option>
            </select>
            <select x-model="statusFilter" class="rounded-lg border border-border bg-white px-3 py-2.5 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                <option value="">Todos los estados</option><option value="online">En línea</option><option value="powered_off">Apagados</option><option value="offline">Fuera de línea</option>
            </select>
            <select x-model="locationFilter" class="rounded-lg border border-border bg-white px-3 py-2.5 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                <option value="">Todas las ubicaciones</option>
                @foreach(App\Models\Location::orderBy('name')->get() as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach
            </select>
        </div>

        <div x-show="selectedFleetKeys.length > 0" x-transition class="mt-4 flex flex-wrap items-center gap-2 border-t border-border pt-4">
            <div class="mr-2 flex items-center gap-2"><span class="flex h-8 min-w-8 items-center justify-center rounded-lg bg-primary/10 px-2 text-sm font-bold text-primary" x-text="selectedFleetKeys.length"></span><span class="text-xs font-semibold text-text-light">seleccionados</span></div>
            <button type="button" @click="submitFleet('power', '1')" :disabled="fleetSubmitting" class="rounded-lg bg-success px-3 py-2 text-xs font-semibold text-white disabled:opacity-50">Encender</button>
            <button type="button" @click="submitFleet('power', '0')" :disabled="fleetSubmitting" class="rounded-lg bg-danger px-3 py-2 text-xs font-semibold text-white disabled:opacity-50">Apagar</button>
            <button type="button" @click="submitFleet('bluetooth', '1')" :disabled="fleetSubmitting" class="rounded-lg border border-primary/20 bg-primary/5 px-3 py-2 text-xs font-semibold text-primary disabled:opacity-50">BT ON</button>
            <button type="button" @click="submitFleet('bluetooth', '0')" :disabled="fleetSubmitting" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-text-light disabled:opacity-50">BT OFF</button>
            <button type="button" @click="showFleetPlayModal=true" class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white">Reproducir</button>
            <button type="button" @click="showFleetUploadModal=true" class="rounded-lg bg-secondary px-3 py-2 text-xs font-semibold text-white">Subir video</button>

            <div class="relative" x-data="{ open: false }">
                <button type="button" @click="open=!open" :disabled="!selectedIds.length" class="inline-flex items-center gap-2 rounded-lg border border-border px-3 py-2 text-xs font-semibold text-text-light disabled:cursor-not-allowed disabled:opacity-40">Administrar Z2<svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg></button>
                <div x-show="open" x-cloak @click.away="open=false" class="absolute right-0 z-40 mt-2 w-56 rounded-lg border border-border bg-white py-1 shadow-xl">
                    <button type="button" @click="open=false; submitBulk('power_on')" class="block w-full px-4 py-2 text-left text-sm hover:bg-surface">Encender Z2 directamente</button>
                    <button type="button" @click="open=false; submitBulk('power_off')" class="block w-full px-4 py-2 text-left text-sm hover:bg-surface">Apagar Z2 directamente</button>
                    <button type="button" @click="open=false; showBulkAssignMediaModal=true" class="block w-full px-4 py-2 text-left text-sm font-medium text-primary hover:bg-surface">Asignar medio Z2</button>
                    <button type="button" @click="open=false; submitBulk('change_group')" class="block w-full px-4 py-2 text-left text-sm hover:bg-surface">Cambiar grupo</button>
                    <button type="button" @click="open=false; submitBulk('change_location')" class="block w-full px-4 py-2 text-left text-sm hover:bg-surface">Cambiar ubicación</button>
                    <button type="button" @click="open=false; submitBulk('enable')" class="block w-full px-4 py-2 text-left text-sm hover:bg-surface">Habilitar</button>
                    <button type="button" @click="open=false; submitBulk('disable')" class="block w-full px-4 py-2 text-left text-sm hover:bg-surface">Deshabilitar</button>
                    <div class="my-1 border-t border-border"></div>
                    <button type="button" @click="open=false; showBulkFormatSdModal=true" class="block w-full px-4 py-2 text-left text-sm font-medium text-danger hover:bg-surface">Formatear SD</button>
                    <button type="button" @click="open=false; submitBulk('unbind')" class="block w-full px-4 py-2 text-left text-sm font-medium text-danger hover:bg-surface">Desvincular</button>
                </div>
            </div>

            <div class="ml-auto flex items-center gap-2">
                <select x-model="bulkGroupId" class="w-36 rounded-lg border border-border px-2 py-2 text-xs"><option value="">Grupo Z2</option>@foreach(App\Models\Group::orderBy('name')->get() as $group)<option value="{{ $group->id }}">{{ $group->name }}</option>@endforeach</select>
                <select x-model="bulkLocationId" class="w-36 rounded-lg border border-border px-2 py-2 text-xs"><option value="">Ubicación Z2</option>@foreach(App\Models\Location::orderBy('name')->get() as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach</select>
                <button type="button" @click="selectedIds=[]; selectedFleetKeys=[]" class="rounded-lg px-3 py-2 text-xs font-semibold text-text-muted hover:bg-surface">Limpiar</button>
            </div>
        </div>

        <form x-ref="fleetCommand" action="{{ route('fleet.command') }}" method="POST" class="hidden">@csrf<input type="hidden" name="command"><input type="hidden" name="value"><template x-for="key in selectedFleetKeys" :key="'command-'+key"><input type="hidden" name="targets[]" :value="key"></template></form>
        <form id="bulk-operation-form" action="{{ route('devices.bulk-operation') }}" method="POST" class="hidden">@csrf<input id="bulk-operation-action" type="hidden" name="action"><input id="bulk-operation-group" type="hidden" name="target_group_id"><input id="bulk-operation-location" type="hidden" name="target_location_id"><template x-for="id in selectedIds" :key="'legacy-'+id"><input type="hidden" name="device_ids[]" :value="id"></template></form>
    </section>

    <section class="overflow-hidden rounded-xl border border-border bg-white shadow-[0_1px_3px_rgba(0,0,0,0.04)]">
        @if($devices->count() > 0 || $wl35Devices->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1050px] text-left text-sm">
                    <thead><tr class="border-b border-border bg-surface"><th class="px-4 py-4"><input type="checkbox" @click="toggleAll()" class="rounded border-border text-primary focus:ring-primary/20"></th><th class="px-4 py-4 font-semibold text-text">Nombre</th><th class="px-4 py-4 font-semibold text-text">Tipo</th><th class="px-4 py-4 font-semibold text-text">Identificador</th><th class="px-4 py-4 font-semibold text-text">Estado</th><th class="px-4 py-4 font-semibold text-text">Ubicación / Grupo</th><th class="px-4 py-4 font-semibold text-text">Video actual</th><th class="px-4 py-4 font-semibold text-text">Último reporte</th><th class="px-4 py-4 text-right font-semibold text-text">Acciones</th></tr></thead>
                    <tbody class="divide-y divide-border">
                        @foreach($devices as $device)
                            @php
                                $mac = strtoupper(str_replace(':', '', (string) $device->mac_address));
                                $fleetKey = 'z2:'.$mac;
                                $live = $fleetZ2Devices->get($mac, []);
                                $isOnline = (bool) ($live['online'] ?? in_array($device->status, ['online', 'active'], true));
                                $isPowered = (bool) ($live['power'] ?? ($device->power_status !== 'off'));
                                $indicatorStatus = !$isOnline ? 'offline' : ($isPowered ? 'online' : 'powered_off');
                                $searchable = strtolower($device->name.' '.$device->mac_address);
                            @endphp
                            <tr class="transition hover:bg-surface/50" x-show="(!search || {{ Js::from($searchable) }}.includes(search.toLowerCase())) && (!protocolFilter || protocolFilter === 'z2') && (!statusFilter || statusFilter === '{{ $indicatorStatus }}') && (!locationFilter || locationFilter === '{{ $device->location_id }}')">
                                <td class="px-4 py-4"><input type="checkbox" data-fleet-selector data-local-id="{{ $device->id }}" value="{{ $fleetKey }}" :checked="selectedFleetKeys.includes({{ Js::from($fleetKey) }})" @change="toggleDevice({{ Js::from($fleetKey) }}, '{{ $device->id }}', $event.target.checked)" class="rounded border-border text-primary focus:ring-primary/20"></td>
                                <td class="px-4 py-4"><div class="flex items-center gap-3"><div class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-100 text-amber-700"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg></div><span class="font-medium text-text">{{ $device->name }}</span></div></td>
                                <td class="px-4 py-4"><span class="rounded-md bg-amber-100 px-2 py-1 text-[10px] font-bold tracking-widest text-amber-700">Z2</span></td>
                                <td class="px-4 py-4 font-mono text-xs text-text-light">{{ $device->mac_address }}</td>
                                <td class="px-4 py-4"><x-device-status-indicator :status="$indicatorStatus" /></td>
                                <td class="px-4 py-4 text-xs text-text-light"><div>{{ $device->location?->name ?? 'Sin ubicación' }}</div><div class="mt-1 text-text-muted">{{ $device->group?->name ?? 'Sin grupo' }}</div></td>
                                <td class="max-w-40 truncate px-4 py-4 text-xs text-text-light" title="{{ $live['current_video'] ?? '' }}">{{ $live['current_video'] ?? '—' }}</td>
                                <td class="px-4 py-4 text-xs text-text-light">{{ $device->last_heartbeat_at ? $device->last_heartbeat_at->diffForHumans() : 'Nunca' }}</td>
                                <td class="px-4 py-4"><div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('devices.show', $device) }}" class="rounded-lg p-1.5 text-text-light hover:bg-info/10 hover:text-info" title="Detalles"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg></a>
                                    <form action="{{ route('devices.power-on', $device) }}" method="POST">@csrf<button class="rounded-lg p-1.5 text-text-light hover:bg-success/10 hover:text-success" title="Encender"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg></button></form>
                                    <form action="{{ route('devices.power-off', $device) }}" method="POST">@csrf<button class="rounded-lg p-1.5 text-text-light hover:bg-danger/10 hover:text-danger" title="Apagar"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg></button></form>
                                    <form action="{{ $device->bluetooth_status === 'on' ? route('devices.bluetooth-off', $device) : route('devices.bluetooth-on', $device) }}" method="POST">@csrf<button class="rounded-lg p-1.5 text-text-light hover:bg-primary/10 hover:text-primary" title="Bluetooth"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6.5 7.5L12 12l-5.5 4.5V7.5zM17.5 7.5L12 12l5.5 4.5V7.5zM12 12v6"/></svg></button></form>
                                    <a href="{{ route('devices.edit', $device) }}" class="rounded-lg p-1.5 text-text-light hover:bg-primary/10 hover:text-primary" title="Editar"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></a>
                                    <button type="button" @click="showDeleteModal=true; deleteId={{ $device->id }}; deleteName={{ Js::from($device->name) }}" class="rounded-lg p-1.5 text-text-light hover:bg-danger/10 hover:text-danger" title="Eliminar"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>
                                </div></td>
                            </tr>
                        @endforeach

                        @foreach($wl35Devices as $device)
                            @php
                                $key = (string) ($device['key'] ?? 'wl35:'.($device['id'] ?? ''));
                                $isOnline = (bool) (($device['online'] ?? false) && ($device['connected'] ?? false));
                                $isPowered = (bool) ($device['power'] ?? false);
                                $indicatorStatus = !$isOnline ? 'offline' : ($isPowered ? 'online' : 'powered_off');
                                $searchable = strtolower(($device['name'] ?? '').' '.($device['id'] ?? '').' '.($device['ip'] ?? ''));
                            @endphp
                            <tr class="transition hover:bg-sky-50/40" x-show="(!search || {{ Js::from($searchable) }}.includes(search.toLowerCase())) && (!protocolFilter || protocolFilter === 'wl35') && (!statusFilter || statusFilter === '{{ $indicatorStatus }}') && !locationFilter">
                                <td class="px-4 py-4"><input type="checkbox" data-fleet-selector value="{{ $key }}" :checked="selectedFleetKeys.includes({{ Js::from($key) }})" @change="toggleDevice({{ Js::from($key) }}, null, $event.target.checked)" class="rounded border-border text-primary focus:ring-primary/20"></td>
                                <td class="px-4 py-4"><div class="flex items-center gap-3"><div class="flex h-8 w-8 items-center justify-center rounded-lg bg-sky-100 text-sky-700"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18.5A6.5 6.5 0 1012 5a6.5 6.5 0 000 13.5zm0 0V22m-3 0h6M12 5V2"/></svg></div><span class="font-medium text-text">{{ $device['name'] ?? $device['id'] ?? 'WL35' }}</span></div></td>
                                <td class="px-4 py-4"><span class="rounded-md bg-sky-100 px-2 py-1 text-[10px] font-bold tracking-widest text-sky-700">WL35</span></td>
                                <td class="px-4 py-4"><div class="font-mono text-xs text-text-light">{{ $device['id'] ?? '—' }}</div><div class="mt-1 text-[10px] text-text-muted">{{ $device['ip'] ?? '' }}</div></td>
                                <td class="px-4 py-4"><x-device-status-indicator :status="$indicatorStatus" /></td>
                                <td class="px-4 py-4 text-xs text-text-muted">Sin ubicación<br>Sin grupo</td>
                                <td class="max-w-40 truncate px-4 py-4 text-xs text-text-light">{{ $device['current_video'] ?? '—' }}</td>
                                <td class="px-4 py-4 text-xs text-text-light">{{ isset($device['last_seen']) ? \Illuminate\Support\Carbon::parse($device['last_seen'])->diffForHumans() : 'Nunca' }}</td>
                                <td class="px-4 py-4"><div class="flex items-center justify-end gap-1">
                                    <form action="{{ route('fleet.command') }}" method="POST" class="flex gap-1">@csrf<input type="hidden" name="command" value="power"><input type="hidden" name="targets[]" value="{{ $key }}"><button name="value" value="1" class="rounded-lg p-1.5 text-text-light hover:bg-success/10 hover:text-success" title="Encender"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg></button><button name="value" value="0" class="rounded-lg p-1.5 text-text-light hover:bg-danger/10 hover:text-danger" title="Apagar"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg></button></form>
                                    <form action="{{ route('fleet.command') }}" method="POST">@csrf<input type="hidden" name="command" value="bluetooth"><input type="hidden" name="targets[]" value="{{ $key }}"><button name="value" value="{{ ($device['bluetooth'] ?? false) ? 0 : 1 }}" class="rounded-lg p-1.5 {{ ($device['bluetooth'] ?? false) ? 'bg-primary/10 text-primary' : 'text-text-light hover:bg-primary/10 hover:text-primary' }}" title="Bluetooth"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6.5 7.5L12 12l-5.5 4.5V7.5zM17.5 7.5L12 12l5.5 4.5V7.5zM12 12v6"/></svg></button></form>
                                    <form action="{{ route('fleet.command') }}" method="POST" class="flex items-center gap-1">@csrf<input type="hidden" name="command" value="play"><input type="hidden" name="targets[]" value="{{ $key }}"><input type="number" name="wl35_video_index" min="1" max="255" value="1" class="w-12 rounded border border-border px-1.5 py-1 text-xs" title="Índice de video"><button class="rounded-lg bg-slate-900 px-2 py-1.5 text-[10px] font-semibold text-white">Play</button></form>
                                </div></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($devices->hasPages())<div class="border-t border-border px-6 py-4">{{ $devices->links() }}</div>@endif
        @else
            <div class="px-6 py-16 text-center"><div class="font-semibold text-text">No hay dispositivos</div><div class="mt-1 text-sm text-text-muted">No se encontraron Z2 locales ni WL35 conectados.</div></div>
        @endif
    </section>

    <div x-show="showDeleteModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4"><div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showDeleteModal=false"></div><div class="relative w-full max-w-md rounded-xl border border-border bg-white p-6 shadow-xl"><h3 class="text-lg font-semibold text-text">¿Eliminar dispositivo Z2?</h3><p class="mt-2 text-sm text-text-light">Se eliminará <strong x-text="deleteName"></strong> del registro local.</p><div class="mt-6 flex justify-end gap-3"><button type="button" @click="showDeleteModal=false" class="rounded-lg border border-border px-4 py-2.5 text-sm">Cancelar</button><form :action="'/devices/'+deleteId" method="POST">@csrf @method('DELETE')<button class="rounded-lg bg-danger px-4 py-2.5 text-sm font-medium text-white">Eliminar</button></form></div></div></div>

    <div x-show="showBulkFormatSdModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4"><div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showBulkFormatSdModal=false"></div><div class="relative w-full max-w-md rounded-xl border border-border bg-white p-6 shadow-xl"><h3 class="text-lg font-semibold text-text">¿Formatear las SD seleccionadas?</h3><p class="mt-2 text-sm text-text-light">Se eliminarán los videos de <strong x-text="selectedIds.length"></strong> dispositivos Z2.</p><div class="mt-6 flex justify-end gap-3"><button type="button" @click="showBulkFormatSdModal=false" class="rounded-lg border border-border px-4 py-2.5 text-sm">Cancelar</button><form action="{{ route('devices.bulk-format-sd') }}" method="POST">@csrf<template x-for="id in selectedIds" :key="'format-'+id"><input type="hidden" name="device_ids[]" :value="id"></template><button class="rounded-lg bg-danger px-4 py-2.5 text-sm font-medium text-white">Formatear Z2</button></form></div></div></div>

    <div x-show="showBulkAssignMediaModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4"><div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showBulkAssignMediaModal=false"></div><div class="relative w-full max-w-md rounded-xl border border-border bg-white p-6 shadow-xl"><h3 class="text-lg font-semibold text-text">Asignar medio a Z2</h3><p class="mt-2 text-sm text-text-light">El mismo archivo se asignará a <strong x-text="selectedIds.length"></strong> dispositivos Z2.</p><form action="{{ route('devices.bulk-assign-media') }}" method="POST" class="mt-5 space-y-4" @submit="bulkAssigningMedia=true">@csrf<template x-for="id in selectedIds" :key="'media-'+id"><input type="hidden" name="device_ids[]" :value="id"></template><select name="media_id" required class="w-full rounded-lg border border-border bg-white px-4 py-2.5 text-sm"><option value="">Seleccionar medio</option>@foreach(App\Models\Media::orderBy('name')->get() as $media)<option value="{{ $media->id }}" @selected((string) old('media_id') === (string) $media->id)>{{ $media->name }}</option>@endforeach</select><div class="flex justify-end gap-3"><button type="button" @click="showBulkAssignMediaModal=false" class="rounded-lg border border-border px-4 py-2.5 text-sm">Cancelar</button><button :disabled="bulkAssigningMedia" class="rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-white disabled:opacity-50" x-text="bulkAssigningMedia ? 'Asignando…' : 'Asignar medio'"></button></div></form></div></div>

    <div x-show="showFleetPlayModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4"><div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showFleetPlayModal=false"></div><div class="relative w-full max-w-lg rounded-xl border border-border bg-white p-6 shadow-xl"><h3 class="text-lg font-semibold text-text">Reproducir en la selección</h3><p class="mt-2 text-sm text-text-light">Indica ambos valores si seleccionaste una combinación de WL35 y Z2.</p><form action="{{ route('fleet.command') }}" method="POST" class="mt-5 space-y-4" @submit="fleetSubmitting=true">@csrf<input type="hidden" name="command" value="play"><template x-for="key in selectedFleetKeys" :key="'play-'+key"><input type="hidden" name="targets[]" :value="key"></template><div class="grid gap-4 sm:grid-cols-2"><label><span class="mb-1.5 block text-xs font-semibold text-text-light">Índice WL35</span><input type="number" name="wl35_video_index" min="1" max="255" value="{{ old('wl35_video_index', 1) }}" class="w-full rounded-lg border border-border px-3 py-2.5 text-sm"></label><label><span class="mb-1.5 block text-xs font-semibold text-text-light">Archivo Z2</span><select name="z2_filename" class="w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm"><option value="">Seleccionar archivo</option>@foreach($fleetMedia as $asset)<option value="{{ $asset['filename'] ?? '' }}">{{ $asset['filename'] ?? 'Archivo' }}</option>@endforeach</select></label></div><div class="flex justify-end gap-3"><button type="button" @click="showFleetPlayModal=false" class="rounded-lg border border-border px-4 py-2.5 text-sm">Cancelar</button><button :disabled="fleetSubmitting" class="rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-medium text-white disabled:opacity-50">Reproducir</button></div></form></div></div>

    <div x-show="showFleetUploadModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4"><div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showFleetUploadModal=false"></div><div class="relative w-full max-w-lg rounded-xl border border-border bg-white p-6 shadow-xl"><h3 class="text-lg font-semibold text-text">Subir y distribuir video</h3><p class="mt-2 text-sm text-text-light">El MP4 se carga una vez y el gateway prepara la versión de cada protocolo.</p><form action="{{ route('fleet.upload') }}" method="POST" enctype="multipart/form-data" class="mt-5 space-y-4" @submit="fleetSubmitting=true">@csrf<template x-for="key in selectedFleetKeys" :key="'upload-'+key"><input type="hidden" name="targets[]" :value="key"></template><input type="file" name="video" accept="video/mp4,.mp4" required class="block w-full rounded-xl border border-dashed border-border bg-surface p-4 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-secondary file:px-3 file:py-2 file:font-semibold file:text-white"><div class="flex justify-end gap-3"><button type="button" @click="showFleetUploadModal=false" class="rounded-lg border border-border px-4 py-2.5 text-sm">Cancelar</button><button :disabled="fleetSubmitting" class="rounded-lg bg-secondary px-4 py-2.5 text-sm font-medium text-white disabled:opacity-50">Subir y distribuir</button></div></form></div></div>
</div>
@endsection
