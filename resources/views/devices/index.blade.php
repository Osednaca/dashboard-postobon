@extends('layouts.app')

@section('title', 'Dispositivos')

@section('content')
@php
    $canOperateDevices = auth()->user()->operatesDevices();
    $groups = $groups ?? \App\Models\Group::orderBy('name')->get();
    $groupId = $groupId ?? null;
    $libraryVideos = $canOperateDevices ? \App\Models\Media::where('mime_type', 'like', 'video/%')->orderBy('name')->get() : collect();
    $initialLocalIds = collect(old('device_ids', []))->map(fn ($id) => (string) $id)->unique()->values();
    $localFleetKeys = $devices->getCollection()->mapWithKeys(function ($device) {
        $mac = strtoupper(str_replace(':', '', (string) $device->mac_address));
        return $mac === '' ? [] : [(string) $device->id => 'z2:'.$mac];
    });
    $initialFleetKeys = collect(old('targets', []))
        ->merge($initialLocalIds->map(fn ($id) => $localFleetKeys->get($id))->filter())
        ->map(fn ($key) => (string) $key)->unique()->values();
    $deviceRows = $devices->getCollection()->map(function ($device) use ($fleetZ2Devices) {
        $mac = strtoupper(str_replace(':', '', (string) $device->mac_address));
        $live = $fleetZ2Devices->get($mac, []);
        $online = (bool) ($live['online'] ?? in_array($device->status, ['online', 'active'], true));
        $powered = (bool) ($live['power'] ?? ($device->power_status !== 'off'));
        return [
            'key' => 'z2:'.$mac, 'localId' => (string) $device->id, 'type' => 'z2',
            'removalKey' => 'z2:'.$device->id,
            'name' => $device->name, 'identifier' => $device->mac_address,
            'status' => !$online ? 'offline' : ($powered ? 'online' : 'powered_off'),
            'powered' => $powered, 'bluetooth' => $device->bluetooth_status === 'on',
            'establishmentId' => (string) $device->establishment_id,
            'establishment' => $device->establishmentProfile?->name ?? $device->establishment ?? 'Sin establecimiento',
            'address' => $device->establishmentProfile?->address ?? $device->address ?? 'Sin dirección',
            'content' => $live['current_video'] ?? null,
            'lastSeen' => $device->last_heartbeat_at?->diffForHumans() ?? 'Sin reporte',
            'detailsUrl' => route('devices.show', $device), 'editUrl' => route('devices.edit', $device),
            'powerOnUrl' => route('devices.power-on', $device), 'powerOffUrl' => route('devices.power-off', $device),
            'bluetoothOnUrl' => route('devices.bluetooth-on', $device), 'bluetoothOffUrl' => route('devices.bluetooth-off', $device),
        ];
    })->concat($wl35Devices->map(function ($device) {
        $online = (bool) (($device['online'] ?? false) && ($device['connected'] ?? false));
        $powered = (bool) ($device['power'] ?? false);
        return [
            'key' => (string) ($device['key'] ?? 'wl35:'.$device['id']), 'localId' => null, 'type' => 'wl35',
            'removalKey' => 'wl35:'.$device['id'],
            'name' => $device['name'] ?? $device['id'], 'identifier' => $device['id'], 'ip' => $device['ip'] ?? '',
            'status' => !$online ? 'offline' : ($powered ? 'online' : 'powered_off'),
            'powered' => $powered, 'bluetooth' => (bool) ($device['bluetooth'] ?? false),
            'establishmentId' => (string) ($device['profile_establishment_id'] ?? ''),
            'establishment' => $device['profile_establishment'] ?? 'Sin establecimiento',
            'address' => $device['profile_address'] ?? 'Sin dirección', 'content' => $device['current_video'] ?? null,
            'lastSeen' => isset($device['last_seen']) ? \Illuminate\Support\Carbon::parse($device['last_seen'])->diffForHumans() : 'Sin reporte',
            'detailsUrl' => route('devices.wl35.show', ['deviceId' => $device['id']]), 'editUrl' => null,
            'powerOnUrl' => route('fleet.command'), 'powerOffUrl' => route('fleet.command'), 'bluetoothOnUrl' => route('fleet.command'), 'bluetoothOffUrl' => route('fleet.command'),
        ];
    }))->map(function ($row) {
        $row = array_merge($row, ['status' => 'unavailable', 'online' => null, 'connected' => null, 'powered' => null, 'bluetooth' => null, 'sessionReady' => null, 'uploading' => null]);
        $row['searchable'] = implode(' ', [$row['name'], $row['identifier'], $row['ip'] ?? '', $row['establishment'], $row['address']]);
        return $row;
    })->values();
@endphp

<div x-data="{
    ...deviceList({{ Js::from($deviceRows) }}),
    activeGroupId: {{ Js::from((string) $groupId) }},
    showDeleteModal: false, showBulkAssignMediaModal: false,
    showFleetPlayModal: false, showFleetUploadModal: false, showFleetFormatModal: false,
    bulkAssigningMedia: false, fleetSubmitting: false,
    fleetUpload: { id: null, status: 'idle', phase: 'idle', progress: 0, fileName: '', error: '', result: null, elapsed: 0 },
    fleetUploadPoller: null, fleetUploadClock: null,
    canOperateDevices: {{ Js::from($canOperateDevices) }},
    fleetUploadStatusTemplate: {{ Js::from(route('fleet.upload.status', ['fleetUpload' => '__UPLOAD_ID__'])) }},
    fleetFormat: { id: null, status: 'idle', progress: 0, error: '', result: null, elapsed: 0 },
    fleetFormatTargets: [], fleetFormatPoller: null, fleetFormatClock: null,
    fleetFormatStatusTemplate: {{ Js::from(route('fleet.operation.status', ['fleetOperation' => '__OPERATION_ID__'])) }},
    deleteId: null, deleteName: '',
    selectedIds: {{ Js::from($initialLocalIds) }},
    selectedFleetKeys: {{ Js::from($initialFleetKeys) }},
    filtersExpanded: false,
    bulkGroupId: '',
    init() {
        if (!this.canOperateDevices) return;
        this.watchDialogs();
        const uploadId = window.localStorage.getItem('fleet-upload-id');
        if (uploadId) {
            this.fleetUpload.id = uploadId;
            this.fleetUpload.status = 'queued';
            this.fleetUpload.phase = 'queued';
            this.fleetUpload.progress = 15;
            this.showFleetUploadModal = true;
            this.startFleetUploadPolling();
        }
        const formatId = window.localStorage.getItem('fleet-format-operation-id');
        if (formatId) {
            this.fleetFormat.id = formatId;
            this.fleetFormat.status = 'queued';
            this.fleetFormat.progress = 10;
            this.showFleetFormatModal = true;
            this.startFleetFormatPolling();
        }
    },
    openFleetUpload() {
        if (this.fleetUpload.status === 'completed' || this.fleetUpload.status === 'failed') {
            this.resetFleetUpload();
        }
        this.showFleetUploadModal = true;
    },
    hideFleetUpload() {
        this.showFleetUploadModal = false;
    },
    dismissFleetUpload() {
        if (this.fleetUpload.status === 'queued' || this.fleetUpload.status === 'processing' || this.fleetUpload.status === 'receiving') {
            this.hideFleetUpload();
            return;
        }
        window.localStorage.removeItem('fleet-upload-id');
        this.stopFleetUploadPolling();
        this.resetFleetUpload();
        this.showFleetUploadModal = false;
    },
    resetFleetUpload() {
        this.fleetUpload = { id: null, status: 'idle', phase: 'idle', progress: 0, fileName: '', error: '', result: null, elapsed: 0 };
        this.$nextTick(() => this.$refs.fleetUploadForm?.reset());
    },
    submitFleetUpload(event) {
        event.preventDefault();
        if (['receiving', 'queued', 'processing'].includes(this.fleetUpload.status)) return;

        const form = event.currentTarget;
        const file = form.querySelector('[name=video]')?.files?.[0];
        if (!file) return;

        this.fleetUpload = { id: null, status: 'receiving', phase: 'receiving', progress: 1, fileName: file.name, error: '', result: null, elapsed: 0 };
        this.startFleetUploadClock();

        const request = new XMLHttpRequest();
        request.open('POST', form.action);
        request.setRequestHeader('Accept', 'application/json');
        request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        request.upload.addEventListener('progress', progressEvent => {
            if (progressEvent.lengthComputable) {
                this.fleetUpload.progress = Math.max(1, Math.round((progressEvent.loaded / progressEvent.total) * 14));
            }
        });
        request.addEventListener('load', () => {
            let payload = {};
            try { payload = JSON.parse(request.responseText || '{}'); } catch (_) {}

            if (request.status < 200 || request.status >= 300 || !payload.upload_id) {
                const validationError = payload.errors ? Object.values(payload.errors).flat()[0] : null;
                this.failFleetUpload(validationError || payload.message || `No fue posible recibir el video (HTTP ${request.status}).`);
                return;
            }

            this.fleetUpload.id = payload.upload_id;
            this.fleetUpload.status = 'queued';
            this.fleetUpload.phase = 'queued';
            this.fleetUpload.progress = 15;
            window.localStorage.setItem('fleet-upload-id', payload.upload_id);
            this.startFleetUploadPolling();
        });
        request.addEventListener('error', () => this.failFleetUpload('Se perdió la conexión mientras se subía el archivo. Revisa tu red e inténtalo nuevamente.'));
        request.send(new FormData(form));
    },
    startFleetUploadPolling() {
        this.stopFleetUploadPolling(false);
        this.startFleetUploadClock();
        this.pollFleetUpload();
        this.fleetUploadPoller = window.setInterval(() => this.pollFleetUpload(), 2500);
    },
    async pollFleetUpload() {
        if (!this.fleetUpload.id) return;
        const url = this.fleetUploadStatusTemplate.replace('__UPLOAD_ID__', encodeURIComponent(this.fleetUpload.id));
        try {
            const response = await fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (response.status === 404 || response.status === 403) {
                window.localStorage.removeItem('fleet-upload-id');
                this.failFleetUpload('Ya no fue posible recuperar este trabajo. Inicia una nueva carga.');
                return;
            }
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const payload = await response.json();
            this.fleetUpload.fileName = payload.filename || this.fleetUpload.fileName;
            this.fleetUpload.status = payload.status;
            this.fleetUpload.phase = payload.phase;
            this.fleetUpload.progress = payload.progress ?? this.fleetUpload.progress;
            this.fleetUpload.error = payload.error || '';
            this.fleetUpload.result = payload.result || null;
            this.fleetUpload.transfers = payload.transfers || [];
            if (payload.started_at && this.fleetUpload.elapsed === 0) {
                this.fleetUpload.elapsed = Math.max(0, Math.floor((Date.now() - new Date(payload.started_at).getTime()) / 1000));
            }

            if (payload.status === 'completed' || payload.status === 'failed') {
                this.stopFleetUploadPolling();
                this.$dispatch('refresh-devices');
            }
        } catch (_) {
            this.fleetUpload.error = 'No se pudo actualizar el progreso. El trabajo continúa en el servidor; se intentará consultar nuevamente.';
        }
    },
    startFleetUploadClock() {
        if (this.fleetUploadClock) return;
        const startedAt = Date.now() - (this.fleetUpload.elapsed * 1000);
        this.fleetUploadClock = window.setInterval(() => {
            this.fleetUpload.elapsed = Math.floor((Date.now() - startedAt) / 1000);
        }, 1000);
    },
    stopFleetUploadPolling(stopClock = true) {
        if (this.fleetUploadPoller) window.clearInterval(this.fleetUploadPoller);
        this.fleetUploadPoller = null;
        if (stopClock && this.fleetUploadClock) window.clearInterval(this.fleetUploadClock);
        if (stopClock) this.fleetUploadClock = null;
    },
    failFleetUpload(message) {
        this.fleetUpload.status = 'failed';
        this.fleetUpload.phase = 'failed';
        this.fleetUpload.error = message;
        this.stopFleetUploadPolling();
    },
    fleetUploadHasFailures() {
        return this.fleetUpload.status === 'failed' || (this.fleetUpload.status === 'completed' && (this.fleetUpload.result?.failed ?? 0) > 0);
    },
    fleetUploadFailures() {
        return (this.fleetUpload.result?.results || []).filter(item => item.success === false);
    },
    fleetUploadFailureMessage(item) {
        if (item.error === 'timeout waiting for WL35 upload ACK') {
            return 'El 35 no confirmó el inicio de carga. No se enviaron bytes de video. Es necesario comprobar la sesión y el almacenamiento del ventilador.';
        }
        return item.error || 'El equipo no confirmó la distribución.';
    },
    fleetUploadTitle() {
        if (this.fleetUpload.status === 'completed' && (this.fleetUpload.result?.failed ?? 0) > 0) {
            return 'Distribución finalizada con errores';
        }
        return {
            receiving: 'Subiendo al servidor', queued: 'Esperando al procesador', uploading_gateway: 'Enviando al gateway',
            distributing: 'Preparando la distribución', converting: 'Convirtiendo el video', awaiting_device: 'Esperando al 35', relaying: 'Transmitiendo al ventilador', finalizing: 'Confirmando resultados', completed: 'Distribución completada', failed: 'La distribución falló'
        }[this.fleetUpload.phase] || 'Preparando la carga';
    },
    fleetUploadDescription() {
        const transfers = (this.fleetUpload.transfers || []).filter(item => item.phase === 'relaying');
        if (transfers.length) {
            const sent = transfers.reduce((sum, item) => sum + item.bytes, 0) / 1000000;
            const total = transfers.reduce((sum, item) => sum + item.total_bytes, 0) / 1000000;
            const speed = transfers.reduce((sum, item) => sum + (item.bytes_per_second || 0), 0) / 1000;
            return `${sent.toFixed(1)} de ${total.toFixed(1)} MB transmitidos · ${speed.toFixed(0)} kB/s. El 35 permanece pausado durante este envío.`;
        }
        if (this.fleetUpload.phase === 'queued' && this.fleetUpload.elapsed > 30) {
            return 'El trabajo sigue esperando al procesador. Si no avanza, el administrador debe revisar el worker de Laravel.';
        }
        if (this.fleetUpload.status === 'completed' && (this.fleetUpload.result?.failed ?? 0) > 0) {
            return 'El proceso terminó, pero uno o más ventiladores rechazaron o no confirmaron la distribución.';
        }
        return {
            receiving: 'El navegador está enviando el MP4. No cierres esta ventana todavía.',
            queued: 'El archivo ya está seguro en el VPS y espera su turno en la cola.',
            uploading_gateway: 'El VPS está transfiriendo el archivo al gateway unificado.',
            distributing: 'El gateway prepara cada formato y lo transmite a los ventiladores seleccionados.',
            converting: 'El servidor está preparando el formato 35. El ventilador continúa reproduciendo durante la conversión.',
            awaiting_device: 'El video está preparado. Falta que el 35 confirme que acepta iniciar la carga.',
            finalizing: 'Los dispositivos están reportando el resultado final.',
            completed: 'El gateway terminó de procesar los equipos seleccionados.',
            failed: 'El archivo dejó de procesarse. Puedes revisar el detalle e intentarlo de nuevo.'
        }[this.fleetUpload.phase] || 'Preparando el trabajo de distribución.';
    },
    fleetUploadElapsed() {
        const minutes = Math.floor(this.fleetUpload.elapsed / 60);
        const seconds = String(this.fleetUpload.elapsed % 60).padStart(2, '0');
        return `${minutes}:${seconds}`;
    },
    openFleetFormat(targets = null) {
        if (['completed', 'failed'].includes(this.fleetFormat.status)) {
            this.resetFleetFormat();
        }
        if (this.fleetFormat.status === 'idle') {
            this.fleetFormatTargets = [...(targets ?? this.selectedFleetKeys)];
        }
        if (!this.fleetFormatTargets.length) return;
        this.showFleetFormatModal = true;
    },
    hideFleetFormat() {
        this.showFleetFormatModal = false;
    },
    dismissFleetFormat() {
        if (['queued', 'processing'].includes(this.fleetFormat.status)) {
            this.hideFleetFormat();
            return;
        }
        window.localStorage.removeItem('fleet-format-operation-id');
        this.stopFleetFormatPolling();
        this.resetFleetFormat();
        this.showFleetFormatModal = false;
    },
    resetFleetFormat() {
        this.fleetFormat = { id: null, status: 'idle', progress: 0, error: '', result: null, elapsed: 0 };
        this.fleetFormatTargets = [];
    },
    async submitFleetFormat(event) {
        event.preventDefault();
        if (['queued', 'processing'].includes(this.fleetFormat.status)) return;

        const form = event.currentTarget;
        this.fleetFormat = { id: null, status: 'queued', progress: 5, error: '', result: null, elapsed: 0 };
        this.startFleetFormatClock();

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(form),
            });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok || !payload.operation_id) {
                const validationError = payload.errors ? Object.values(payload.errors).flat()[0] : null;
                throw new Error(validationError || payload.message || `No fue posible iniciar el formateo (HTTP ${response.status}).`);
            }

            this.fleetFormat.id = payload.operation_id;
            this.fleetFormat.progress = 10;
            window.localStorage.setItem('fleet-format-operation-id', payload.operation_id);
            this.startFleetFormatPolling();
        } catch (error) {
            this.failFleetFormat(error.message || 'No fue posible iniciar el formateo.');
        }
    },
    startFleetFormatPolling() {
        this.stopFleetFormatPolling(false);
        this.startFleetFormatClock();
        this.pollFleetFormat();
        this.fleetFormatPoller = window.setInterval(() => this.pollFleetFormat(), 2500);
    },
    async pollFleetFormat() {
        if (!this.fleetFormat.id) return;
        const url = this.fleetFormatStatusTemplate.replace('__OPERATION_ID__', encodeURIComponent(this.fleetFormat.id));
        try {
            const response = await fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (response.status === 404 || response.status === 403) {
                window.localStorage.removeItem('fleet-format-operation-id');
                this.failFleetFormat('Ya no fue posible recuperar este formateo. Inicia una nueva operación.');
                return;
            }
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const payload = await response.json();
            this.fleetFormat.status = payload.status;
            this.fleetFormat.progress = payload.progress ?? this.fleetFormat.progress;
            this.fleetFormat.error = payload.error || '';
            this.fleetFormat.result = payload.result || null;
            if (Array.isArray(payload.targets) && payload.targets.length) this.fleetFormatTargets = payload.targets;
            if (payload.started_at && this.fleetFormat.elapsed === 0) {
                this.fleetFormat.elapsed = Math.max(0, Math.floor((Date.now() - new Date(payload.started_at).getTime()) / 1000));
            }
            if (['completed', 'failed'].includes(payload.status)) { this.stopFleetFormatPolling(); this.$dispatch('refresh-devices'); }
        } catch (_) {
            this.fleetFormat.error = 'No se pudo actualizar el progreso. El formateo continúa en el servidor y se consultará nuevamente.';
        }
    },
    startFleetFormatClock() {
        if (this.fleetFormatClock) return;
        const startedAt = Date.now() - (this.fleetFormat.elapsed * 1000);
        this.fleetFormatClock = window.setInterval(() => {
            this.fleetFormat.elapsed = Math.floor((Date.now() - startedAt) / 1000);
        }, 1000);
    },
    stopFleetFormatPolling(stopClock = true) {
        if (this.fleetFormatPoller) window.clearInterval(this.fleetFormatPoller);
        this.fleetFormatPoller = null;
        if (stopClock && this.fleetFormatClock) window.clearInterval(this.fleetFormatClock);
        if (stopClock) this.fleetFormatClock = null;
    },
    failFleetFormat(message) {
        this.fleetFormat.status = 'failed';
        this.fleetFormat.progress = 100;
        this.fleetFormat.error = message;
        this.stopFleetFormatPolling();
    },
    retryFleetFormat() {
        const targets = [...this.fleetFormatTargets];
        window.localStorage.removeItem('fleet-format-operation-id');
        this.resetFleetFormat();
        this.openFleetFormat(targets);
    },
    fleetFormatTitle() {
        if (this.fleetFormat.status === 'completed' && (this.fleetFormat.result?.failed ?? 0) > 0) return 'Formateo finalizado con errores';
        return { queued: 'Formateo en cola', processing: 'Formateando almacenamiento', completed: 'Formateo completado', failed: 'El formateo falló' }[this.fleetFormat.status] || 'Confirmar formateo';
    },
    fleetFormatDescription() {
        if (this.fleetFormat.status === 'queued' && this.fleetFormat.elapsed > 30) {
            return 'La operación espera al procesador. Si no avanza, el administrador debe revisar el worker de Laravel.';
        }
        if (this.fleetFormat.status === 'processing') return 'Los 42 usan su formateo nativo. En cada 35 se están eliminando los videos uno por uno.';
        if (this.fleetFormat.status === 'completed') return 'Todos los equipos terminaron de responder. Revisa el resumen antes de actualizar.';
        if (this.fleetFormat.status === 'failed') return 'La operación se interrumpió. El detalle indica qué debes revisar antes de reintentar.';
        return 'Esta acción elimina de forma permanente todos los videos de los equipos seleccionados.';
    },
    fleetFormatElapsed() {
        const minutes = Math.floor(this.fleetFormat.elapsed / 60);
        const seconds = String(this.fleetFormat.elapsed % 60).padStart(2, '0');
        return `${minutes}:${seconds}`;
    },
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
        document.getElementById('bulk-operation-form').submit();
    },
    async submitFleet(command, value) {
        if (!this.selectedFleetKeys.length || this.fleetSubmitting) return;
        const form = this.$refs.fleetCommand;
        form.querySelector('[name=command]').value = command;
        form.querySelector('[name=value]').value = value;
        await this.sendDeviceCommand(form);
    }
}" class="device-list space-y-5" @keydown="dialogKeydown($event)" @device-snapshot="applySnapshot($event.detail)">
    <x-device-live-feedback />
    <div x-data="devicePreviews(@js(route('devices.previews')))" @refresh-devices.window="refresh(true)" hidden></div>
    <header class="device-list-heading">
        <div><h1 class="text-2xl font-bold tracking-tight text-text">Dispositivos</h1><p class="mt-1 text-sm text-text-light">Administra tus dispositivos 42 y 35 desde un solo lugar.</p></div>
        <div class="device-list-header-actions">
            <button type="button" @click="$dispatch('refresh-devices')" class="device-list-button"><x-device-action-icon name="refresh" />Actualizar</button>
            @if($canOperateDevices)
            <a href="{{ route('devices.create') }}" class="device-list-button device-list-button-primary"><x-device-action-icon name="plus" />Nuevo dispositivo <span class="sr-only">42</span></a>
            @endif
        </div>
    </header>
    <section aria-label="Resumen de dispositivos" class="device-list-stats">
        @foreach([['', 'Total', 'monitor', 'blue'], ['online', 'En línea', 'dot', 'green'], ['offline', 'Fuera de línea', 'dot', 'red'], ['powered_off', 'Apagados', 'power', 'amber']] as [$filter, $label, $icon, $tone])
            <div class="device-list-stat"><span class="device-list-stat-icon device-list-tone-{{ $tone }}"><x-device-action-icon :name="$icon" /></span><div><strong class="block text-xl font-bold tabular-nums" x-text="{{ $filter === '' ? 'visibleRows().length' : "countStatus('".$filter."')" }}">{{ $filter === '' ? $deviceRows->count() : $deviceRows->where('status', $filter)->count() }}</strong><span class="text-xs text-text-light">{{ $label }}</span></div></div>
        @endforeach
    </section>
    <p class="!mt-2 text-xs text-text-light"><span class="hidden sm:inline">Resumen de los equipos visibles en esta página · {{ $devices->total() }} 42 registrados y {{ $wl35Devices->count() }} 35 en la lista. </span>El grupo filtra toda la lista; los demás filtros se aplican a esta página.</p>

    @if($gatewayError)
        <div class="flex items-start gap-3 rounded-xl border border-warning/30 bg-warning/10 px-4 py-3 text-sm text-amber-900">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-warning" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <div>
                <div class="font-semibold">Los 42 siguen disponibles, pero no fue posible cargar los 35.</div>
                <div class="mt-1 text-xs leading-5 text-amber-800">{{ $gatewayError }}</div>
            </div>
        </div>
    @elseif(!$gatewayConfigured)
        <div class="rounded-xl border border-warning/30 bg-warning/10 px-4 py-3 text-sm text-amber-900">
            Los 35 ya se pueden consultar, pero las órdenes unificadas requieren configurar <code class="font-semibold">UNIFIED_FLEET_API_TOKEN</code> con el mismo valor de <code class="font-semibold">ADMIN_TOKEN</code> del gateway.
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
        <form action="{{ route('devices.index') }}" method="GET" class="mb-4 flex flex-wrap items-center gap-3">
            <label for="device-group-filter" class="text-sm font-semibold text-text">Grupo</label>
            <select id="device-group-filter" name="group_id" @change="$el.form.requestSubmit()" class="min-w-0 flex-1 rounded-lg border border-border bg-white px-3 py-2.5 text-sm sm:max-w-sm">
                <option value="">Todos los grupos</option>
                @foreach($groups as $group)<option value="{{ $group->id }}" @selected((string) $groupId === (string) $group->id)>{{ $group->name }}</option>@endforeach
            </select>
            <button type="submit" class="rounded-lg border border-border px-3 py-2 text-sm">Filtrar grupo</button>
        </form>
        <div class="device-filter-grid grid gap-3 md:grid-cols-2 xl:grid-cols-5" :class="filtersExpanded ? 'device-filters-expanded' : ''">
            <label class="relative xl:col-span-2">
                <svg class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="search" x-model="search" aria-label="Buscar dispositivos" placeholder="Buscar nombre, MAC o identificador…" class="w-full rounded-lg border border-border py-2.5 pl-10 pr-4 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20">
            </label>
            <button type="button" @click="filtersExpanded = !filtersExpanded" :aria-expanded="filtersExpanded" class="device-filter-toggle"><span x-text="filtersExpanded ? 'Ocultar filtros' : 'Filtros'"></span><span x-show="[protocolFilter, statusFilter, establishmentFilter].filter(Boolean).length" class="rounded bg-primary/10 px-2 text-primary" x-text="[protocolFilter, statusFilter, establishmentFilter].filter(Boolean).length"></span><span aria-hidden="true" x-text="filtersExpanded ? '−' : '+'"></span></button>
            <select x-model="protocolFilter" aria-label="Tipo de dispositivo" class="min-w-0 w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                <option value="">Todos los tipos</option><option value="z2">42</option><option value="wl35">35</option>
            </select>
            <select x-model="statusFilter" aria-label="Estado" class="min-w-0 w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                <option value="">Todos los estados</option><option value="online">En línea</option><option value="powered_off">Apagados</option><option value="offline">Fuera de línea</option><option value="unknown">Energía sin confirmar</option><option value="unavailable">Estado no disponible</option>
            </select>
            <select x-model="establishmentFilter" aria-label="Establecimiento" class="min-w-0 w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
                <option value="">Todos los establecimientos</option>
                @foreach($establishments as $establishment)<option value="{{ $establishment->id }}">{{ $establishment->name }}</option>@endforeach
            </select>
        </div>

        @if($canOperateDevices)
        <div x-show="selectedFleetKeys.length > 0" x-transition class="mt-4 flex flex-wrap items-center gap-2 border-t border-border pt-4">
            <div class="mr-2 flex items-center gap-2"><span class="flex h-8 min-w-8 items-center justify-center rounded-lg bg-primary/10 px-2 text-sm font-bold text-primary" x-text="selectedFleetKeys.length"></span><span class="text-xs font-semibold text-text-light">seleccionados</span></div>
            <button type="button" @click="submitFleet('power', '1')" :disabled="fleetSubmitting || commandBusy" class="rounded-lg bg-success px-3 py-2 text-xs font-semibold text-white disabled:opacity-50">Encender</button>
            <button type="button" @click="submitFleet('power', '0')" :disabled="fleetSubmitting || commandBusy" class="rounded-lg bg-danger px-3 py-2 text-xs font-semibold text-white disabled:opacity-50">Apagar</button>
            <button type="button" @click="submitFleet('bluetooth', '1')" :disabled="fleetSubmitting || commandBusy" class="rounded-lg border border-primary/20 bg-primary/5 px-3 py-2 text-xs font-semibold text-primary disabled:opacity-50">Encender Bluetooth</button>
            <button type="button" @click="submitFleet('bluetooth', '0')" :disabled="fleetSubmitting || commandBusy" class="rounded-lg border border-border px-3 py-2 text-xs font-semibold text-text-light disabled:opacity-50">Apagar Bluetooth</button>
            <button type="button" @click="openPlayback()" class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white">Reproducir</button>
            <button type="button" @click="openVideoRemoval()" :disabled="!visibleSelectedRows().length || removalBusy" class="rounded-lg border border-danger/30 bg-danger/5 px-3 py-2 text-xs font-semibold text-danger disabled:opacity-50">Quitar video</button>
            <button type="button" @click="openFleetUpload()" class="rounded-lg bg-secondary px-3 py-2 text-xs font-semibold text-white">Subir video</button>
            <button type="button" @click="openFleetFormat()" class="rounded-lg border border-danger/30 bg-danger/5 px-3 py-2 text-xs font-semibold text-danger transition hover:bg-danger/10">Formatear SD</button>

            <div class="relative" x-data="{ open: false }">
                <button type="button" @click="open=!open" :disabled="!selectedIds.length" class="inline-flex items-center gap-2 rounded-lg border border-border px-3 py-2 text-xs font-semibold text-text-light disabled:cursor-not-allowed disabled:opacity-40">Administrar 42<svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg></button>
                <div x-show="open" x-cloak @click.away="open=false" class="absolute left-0 z-40 mt-2 w-56 rounded-lg border border-border bg-white py-1 shadow-xl">
                    <button type="button" @click="open=false; submitBulk('power_on')" class="block w-full px-4 py-2 text-left text-sm hover:bg-surface">Encender 42 directamente</button>
                    <button type="button" @click="open=false; submitBulk('power_off')" class="block w-full px-4 py-2 text-left text-sm hover:bg-surface">Apagar 42 directamente</button>
                    <button type="button" @click="open=false; showBulkAssignMediaModal=true" class="block w-full px-4 py-2 text-left text-sm font-medium text-primary hover:bg-surface">Asignar medio 42</button>
                    <button type="button" @click="open=false; submitBulk('change_group')" class="block w-full px-4 py-2 text-left text-sm hover:bg-surface">Cambiar grupo</button>
                    <button type="button" @click="open=false; submitBulk('enable')" class="block w-full px-4 py-2 text-left text-sm hover:bg-surface">Habilitar</button>
                    <button type="button" @click="open=false; submitBulk('disable')" class="block w-full px-4 py-2 text-left text-sm hover:bg-surface">Deshabilitar</button>
                    <div class="my-1 border-t border-border"></div>
                    <button type="button" @click="open=false; submitBulk('unbind')" class="block w-full px-4 py-2 text-left text-sm font-medium text-danger hover:bg-surface">Desvincular</button>
                </div>
            </div>

            <div class="flex w-full flex-wrap items-center gap-2 xl:ml-auto xl:w-auto">
                <select x-model="bulkGroupId" aria-label="Grupo para los 42 seleccionados" class="min-w-0 flex-1 rounded-lg border border-border px-2 py-2 text-xs xl:w-36 xl:flex-none"><option value="">Grupo 42</option>@foreach(App\Models\Group::orderBy('name')->get() as $group)<option value="{{ $group->id }}">{{ $group->name }}</option>@endforeach</select>
                <button type="button" @click="selectedIds=[]; selectedFleetKeys=[]" class="rounded-lg px-3 py-2 text-xs font-semibold text-text-muted hover:bg-surface">Limpiar</button>
            </div>
        </div>

        <form x-ref="fleetCommand" action="{{ route('fleet.command') }}" method="POST" class="hidden">@csrf<input type="hidden" name="command"><input type="hidden" name="value"><template x-for="key in selectedFleetKeys" :key="'command-'+key"><input type="hidden" name="targets[]" :value="key"></template></form>
        <form id="bulk-operation-form" action="{{ route('devices.bulk-operation') }}" method="POST" class="hidden">@csrf<input id="bulk-operation-action" type="hidden" name="action"><input id="bulk-operation-group" type="hidden" name="target_group_id"><template x-for="id in selectedIds" :key="'legacy-'+id"><input type="hidden" name="device_ids[]" :value="id"></template></form>
        @endif
    </section>

    @include('devices.partials.device-list-table')

    @if($canOperateDevices)
    <div x-show="showVideoRemovalModal" data-dialog="showVideoRemovalModal" role="dialog" aria-modal="true" aria-labelledby="remove-video-title" :aria-busy="removalBusy" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="closeVideoRemoval()"></div>
        <div class="relative max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl border border-border bg-white p-6 shadow-xl">
            <h3 id="remove-video-title" class="text-lg font-semibold text-text">Quitar video de los dispositivos</h3>
            <p class="mt-2 text-sm text-text-light">Se solicitará quitar el video de <strong x-text="removalTargets.length"></strong> equipos visibles seleccionados. El archivo seguirá disponible en Medios.</p>
            @if($groupId)<p class="mt-2 text-sm font-semibold text-text">Grupo: {{ $groups->firstWhere('id', $groupId)?->name }}</p>@endif
            <ul class="mt-3 max-h-32 space-y-1 overflow-y-auto rounded-lg bg-surface p-3 text-sm" aria-label="Dispositivos que recibirán la solicitud">
                <template x-for="target in removalTargets" :key="target.key"><li><span x-text="target.name"></span> <span class="text-xs text-text-muted" x-text="target.identifier"></span></li></template>
            </ul>
            <form action="{{ route('devices.bulk-remove-video') }}" method="POST" @submit.prevent="submitVideoRemoval($event.currentTarget)" class="mt-4 space-y-4">
                @csrf
                <div>
                    <label for="remove-video-media" class="block text-sm font-semibold text-text">Video que se quitará</label>
                    <select id="remove-video-media" x-model="removalMediaId" required :disabled="removalBusy || removalAttempted" class="mt-2 w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                        <option value="">Seleccionar video de Medios</option>
                        @foreach($libraryVideos as $video)<option value="{{ $video->id }}">{{ $video->name }}</option>@endforeach
                    </select>
                    <p class="mt-2 text-xs text-text-light">Si un equipo no tiene una asociación conocida con este video, se indicará en el resultado. Los videos cargados directamente se pueden revisar desde el detalle del equipo.</p>
                    @if($libraryVideos->isEmpty())<p class="mt-2 text-sm text-text-light">No hay videos en Medios para seleccionar.</p>@endif
                </div>
                <p x-show="removalBusy" role="status" class="text-sm text-text-light">Enviando solicitudes a los equipos…</p>
                <p x-show="removalError" x-text="removalError" role="alert" class="rounded-lg bg-danger/10 p-3 text-sm text-danger"></p>
                <div x-show="removalResults.length" aria-live="polite" class="space-y-3">
                    <p class="text-xs text-text-light">Una solicitud aceptada aún requiere comprobar el contenido reportado por el equipo.</p>
                    <template x-for="result in removalResults" :key="result.key">
                        <div class="rounded-lg border border-border p-3 text-sm"><strong x-text="result.name"></strong><p :class="result.status === 'accepted' ? 'text-success' : 'text-danger'" x-text="result.status === 'accepted' ? 'Solicitud aceptada' : 'No se pudo procesar'"></p><p class="mt-1 text-text-light" x-text="result.message"></p></div>
                    </template>
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" @click="closeVideoRemoval()" :disabled="removalBusy" class="rounded-lg border border-border px-4 py-2.5 text-sm disabled:opacity-50" x-text="removalAttempted ? 'Cerrar' : 'Cancelar'"></button>
                    <button type="submit" x-show="!removalAttempted" :disabled="removalBusy || !removalMediaId || !removalTargets.length" class="rounded-lg bg-danger px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50">Confirmar: quitar video</button>
                </div>
            </form>
        </div>
    </div>
    <div x-show="showDeleteModal" data-dialog="showDeleteModal" role="dialog" aria-modal="true" aria-label="Eliminar dispositivo" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4"><div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showDeleteModal=false"></div><div class="relative w-full max-w-md rounded-xl border border-border bg-white p-6 shadow-xl"><h3 class="text-lg font-semibold text-text">¿Eliminar dispositivo 42?</h3><p class="mt-2 text-sm text-text-light">Se retirará <strong x-text="deleteName"></strong> del dashboard, su establecimiento y grupo. Podrás registrarlo de nuevo; se desvincularán las campañas y programaciones existentes.</p><p class="mt-2 text-sm text-text-light">Se conserva el historial. La nube y la configuración del ventilador no se borran. Las órdenes ya enviadas no pueden revocarse. Revisa las reglas o solicitudes creadas durante esta operación.</p><div class="mt-6 flex justify-end gap-3"><button type="button" @click="showDeleteModal=false" class="rounded-lg border border-border px-4 py-2.5 text-sm">Cancelar</button><form :action="'/devices/'+deleteId" method="POST">@csrf @method('DELETE')<button class="rounded-lg bg-danger px-4 py-2.5 text-sm font-medium text-white">Eliminar</button></form></div></div></div>

    <div x-show="showBulkAssignMediaModal" data-dialog="showBulkAssignMediaModal" role="dialog" aria-modal="true" aria-label="Asignar medio a 42" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4"><div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showBulkAssignMediaModal=false"></div><div class="relative w-full max-w-md rounded-xl border border-border bg-white p-6 shadow-xl"><h3 class="text-lg font-semibold text-text">Asignar medio a 42</h3><p class="mt-2 text-sm text-text-light">El mismo archivo se asignará a <strong x-text="selectedIds.length"></strong> dispositivos 42.</p><form action="{{ route('devices.bulk-assign-media') }}" method="POST" class="mt-5 space-y-4" @submit="bulkAssigningMedia=true">@csrf<template x-for="id in selectedIds" :key="'media-'+id"><input type="hidden" name="device_ids[]" :value="id"></template><select name="media_id" required class="w-full rounded-lg border border-border bg-white px-4 py-2.5 text-sm"><option value="">Seleccionar medio</option>@foreach(App\Models\Media::orderBy('name')->get() as $media)<option value="{{ $media->id }}" @selected((string) old('media_id') === (string) $media->id)>{{ $media->name }}</option>@endforeach</select><div class="flex justify-end gap-3"><button type="button" @click="showBulkAssignMediaModal=false" class="rounded-lg border border-border px-4 py-2.5 text-sm">Cancelar</button><button :disabled="bulkAssigningMedia" class="rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-white disabled:opacity-50" x-text="bulkAssigningMedia ? 'Asignando…' : 'Asignar medio'"></button></div></form></div></div>

    <div x-show="showFleetPlayModal" data-dialog="showFleetPlayModal" role="dialog" aria-modal="true" aria-label="Reproducir contenido" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showFleetPlayModal=false"></div>
        <div class="relative max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl border border-border bg-white p-6 shadow-xl">
            <h3 class="mb-5 text-lg font-semibold text-text">Reproducir en <span x-text="playbackName"></span></h3>
            <x-library-playback :media="$libraryVideos" targets-expression="playbackTargets" />
            <details class="mt-5 border-t border-border pt-4">
                <summary class="cursor-pointer text-sm font-semibold text-text-light">Reproducir contenido ya almacenado</summary>
                <p class="mt-2 text-xs text-text-light">Usa el índice 35 o el archivo 42 disponible en el dispositivo.</p>
                <form action="{{ route('fleet.command') }}" method="POST" class="mt-4 space-y-4" @submit="fleetSubmitting=true">
                    @csrf
                    <input type="hidden" name="command" value="play">
                    <template x-for="key in playbackTargets" :key="'play-'+key"><input type="hidden" name="targets[]" :value="key"></template>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label><span class="mb-1.5 block text-xs font-semibold text-text-light">Índice 35</span><input type="number" name="wl35_video_index" min="1" max="255" value="{{ old('wl35_video_index', 1) }}" class="w-full rounded-lg border border-border px-3 py-2.5 text-sm"></label>
                        <label>
                            <span class="mb-1.5 block text-xs font-semibold text-text-light">Archivo 42</span>
                            <select name="z2_filename" class="w-full rounded-lg border border-border bg-white px-3 py-2.5 text-sm">
                                <option value="">Seleccionar archivo</option>
                                @foreach($fleetMedia as $asset)<option value="{{ $asset['filename'] ?? '' }}">{{ $asset['filename'] ?? 'Archivo' }}</option>@endforeach
                            </select>
                        </label>
                    </div>
                    <button :disabled="fleetSubmitting || commandBusy" class="rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-medium text-white disabled:opacity-50">Reproducir almacenado</button>
                </form>
            </details>
            <div class="mt-5 flex justify-end"><button type="button" @click="showFleetPlayModal=false" class="rounded-lg border border-border px-4 py-2.5 text-sm">Cerrar</button></div>
        </div>
    </div>

    <div x-show="fleetFormat.status !== 'idle' && !showFleetFormatModal" x-cloak
         class="fixed bottom-5 left-5 z-40 w-[min(24rem,calc(100vw-2.5rem))] rounded-xl bg-slate-900 p-4 text-white shadow-[0_14px_35px_rgba(15,23,42,0.28)]">
        <button type="button" @click="showFleetFormatModal=true" class="flex w-full items-center gap-3 text-left">
            <svg x-show="!['completed', 'failed'].includes(fleetFormat.status)" class="h-5 w-5 shrink-0 animate-spin text-red-300" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3"></circle><path class="opacity-90" fill="currentColor" d="M21 12a9 9 0 00-9-9v3a6 6 0 016 6h3z"></path></svg>
            <svg x-show="fleetFormat.status === 'completed'" class="h-5 w-5 shrink-0 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <svg x-show="fleetFormat.status === 'failed'" class="h-5 w-5 shrink-0 text-red-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            <span class="min-w-0 flex-1"><span class="block truncate text-sm font-semibold" x-text="fleetFormatTitle()"></span><span class="mt-0.5 block text-xs text-slate-300">Abrir detalles · <span class="font-mono tabular-nums" x-text="fleetFormatElapsed()"></span></span></span>
            <span x-show="fleetFormat.status !== 'failed'" class="text-xs font-semibold tabular-nums text-red-300" x-text="fleetFormat.progress + '%' "></span>
        </button>
    </div>

    <div x-show="showFleetFormatModal" data-dialog="showFleetFormatModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4"
         role="dialog" aria-modal="true" aria-labelledby="fleet-format-title">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="if (!['queued', 'processing'].includes(fleetFormat.status)) dismissFleetFormat()"></div>
        <div class="relative w-full max-w-lg overflow-hidden rounded-xl bg-white shadow-[0_24px_70px_rgba(15,23,42,0.28)]">
            <div class="border-b border-danger/15 bg-danger/5 px-6 py-5 sm:px-7">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-start gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-danger/10 text-danger"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 7h14M9 11v6m6-6v6M8 7l1-3h6l1 3m1 0-1 13H8L7 7"/></svg></span>
                        <div><h3 id="fleet-format-title" class="text-lg font-semibold text-text" x-text="fleetFormatTitle()"></h3><p class="mt-1 text-sm leading-6 text-text-light" x-text="fleetFormatDescription()"></p></div>
                    </div>
                    <button type="button" @click="dismissFleetFormat()" class="rounded-lg p-2 text-text-muted transition hover:bg-white hover:text-text" aria-label="Cerrar"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                </div>
            </div>

            <form action="{{ route('fleet.command') }}" method="POST" class="space-y-5 p-6 sm:p-7" @submit="submitFleetFormat($event)">
                @csrf
                <input type="hidden" name="command" value="format_sd">
                <template x-for="key in fleetFormatTargets" :key="'format-fleet-'+key"><input type="hidden" name="targets[]" :value="key"></template>

                <div x-show="fleetFormat.status === 'idle'" class="space-y-4">
                    <div class="rounded-xl border border-danger/20 bg-red-50 px-4 py-3 text-sm leading-6 text-red-900"><strong>Esta acción no se puede deshacer.</strong> Se borrarán todos los videos de <span class="font-semibold" x-text="fleetFormatTargets.length"></span> equipos.</div>
                    <div class="grid grid-cols-2 gap-3 text-center">
                        <div class="rounded-xl bg-surface p-3"><div class="text-xl font-bold tabular-nums text-text" x-text="fleetFormatTargets.filter(key => key.startsWith('wl35:')).length"></div><div class="text-xs text-text-muted">35 · borrado secuencial</div></div>
                        <div class="rounded-xl bg-surface p-3"><div class="text-xl font-bold tabular-nums text-text" x-text="fleetFormatTargets.filter(key => key.startsWith('z2:')).length"></div><div class="text-xs text-text-muted">42 · formateo nativo</div></div>
                    </div>
                </div>

                <div x-show="fleetFormat.status !== 'idle'" x-cloak class="space-y-4" aria-live="polite" aria-atomic="true">
                    <div class="flex items-center justify-between gap-3 text-sm"><span class="font-medium text-text-light" x-text="fleetFormatDescription()"></span><span class="shrink-0 font-mono text-xs tabular-nums text-text-muted" x-text="fleetFormatElapsed()"></span></div>
                    <div x-show="fleetFormat.status !== 'failed'" class="h-2 overflow-hidden rounded-full bg-slate-100" role="progressbar" aria-label="Progreso del formateo" :aria-valuenow="fleetFormat.progress" aria-valuemin="0" aria-valuemax="100"><div class="h-full rounded-full bg-danger transition-[width] duration-500" :class="!['completed', 'failed'].includes(fleetFormat.status) ? 'animate-pulse' : ''" :style="`width: ${fleetFormat.progress}%`"></div></div>
                    <div x-show="fleetFormat.error" class="rounded-lg px-3 py-2.5 text-xs leading-5" :class="fleetFormat.status === 'failed' ? 'bg-danger/10 text-danger' : 'bg-warning/10 text-amber-800'" x-text="fleetFormat.error"></div>
                    <div x-show="fleetFormat.status === 'completed' && fleetFormat.result" class="grid grid-cols-3 gap-3 rounded-xl bg-surface p-4 text-center">
                        <div><div class="text-lg font-bold tabular-nums text-text" x-text="fleetFormat.result?.total ?? 0"></div><div class="text-[11px] text-text-muted">Seleccionados</div></div>
                        <div><div class="text-lg font-bold tabular-nums text-success" x-text="fleetFormat.result?.succeeded ?? 0"></div><div class="text-[11px] text-text-muted">Completados</div></div>
                        <div><div class="text-lg font-bold tabular-nums text-danger" x-text="fleetFormat.result?.failed ?? 0"></div><div class="text-[11px] text-text-muted">Fallidos</div></div>
                    </div>
                    <div x-show="fleetFormat.status === 'completed' && fleetFormat.result?.results?.length" class="max-h-40 space-y-2 overflow-y-auto rounded-xl border border-border p-3">
                        <template x-for="result in (fleetFormat.result?.results ?? [])" :key="result.key">
                            <div class="flex items-start gap-2 text-xs"><span class="mt-1 h-2 w-2 shrink-0 rounded-full" :class="result.success ? 'bg-success' : 'bg-danger'"></span><div class="min-w-0 flex-1"><div class="truncate font-semibold text-text" x-text="result.key"></div><div class="mt-0.5 text-text-muted" x-text="result.success ? (result.deleted_videos !== undefined ? `${result.deleted_videos} videos eliminados` : 'SD formateada') : (result.error || 'El equipo no confirmó el formateo')"></div></div></div>
                        </template>
                    </div>
                </div>

                <div class="flex flex-wrap justify-end gap-3 border-t border-border pt-4">
                    <button type="button" x-show="['queued', 'processing'].includes(fleetFormat.status)" @click="hideFleetFormat()" class="rounded-lg border border-border px-4 py-2.5 text-sm font-medium text-text-light transition hover:bg-surface">Ocultar y continuar</button>
                    <button type="button" x-show="fleetFormat.status === 'idle'" @click="dismissFleetFormat()" class="rounded-lg border border-border px-4 py-2.5 text-sm font-medium text-text-light transition hover:bg-surface">Cancelar</button>
                    <button type="submit" x-show="fleetFormat.status === 'idle'" class="rounded-lg bg-danger px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-danger/90 focus:outline-none focus:ring-2 focus:ring-danger/30">Sí, borrar todos los videos</button>
                    <button type="button" x-show="fleetFormat.status === 'failed'" @click="retryFleetFormat()" class="rounded-lg bg-danger px-4 py-2.5 text-sm font-semibold text-white">Preparar otro intento</button>
                    <button type="button" x-show="fleetFormat.status === 'completed'" @click="window.localStorage.removeItem('fleet-format-operation-id'); window.location.reload()" class="rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white">Cerrar y actualizar</button>
                </div>
            </form>
        </div>
    </div>

    <div x-show="fleetUpload.status !== 'idle' && !showFleetUploadModal" x-cloak
         class="fixed bottom-5 right-5 z-40 w-[min(24rem,calc(100vw-2.5rem))] rounded-xl bg-slate-900 p-4 text-white shadow-[0_14px_35px_rgba(15,23,42,0.28)]">
        <button type="button" @click="showFleetUploadModal=true" class="flex w-full items-center gap-3 text-left">
            <svg x-show="!['completed', 'failed'].includes(fleetUpload.status)" class="h-5 w-5 shrink-0 animate-spin text-sky-300" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3"></circle>
                <path class="opacity-90" fill="currentColor" d="M21 12a9 9 0 00-9-9v3a6 6 0 016 6h3z"></path>
            </svg>
            <svg x-show="fleetUpload.status === 'completed' && !fleetUploadHasFailures()" class="h-5 w-5 shrink-0 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <svg x-show="fleetUploadHasFailures()" class="h-5 w-5 shrink-0 text-red-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            <span class="min-w-0 flex-1">
                <span class="block truncate text-sm font-semibold" x-text="fleetUploadTitle()"></span>
                <span class="mt-0.5 block text-xs text-slate-300">Abrir detalles · <span class="font-mono tabular-nums" x-text="fleetUploadElapsed()"></span></span>
            </span>
            <span class="text-xs font-semibold tabular-nums text-sky-300" x-text="fleetUpload.progress + '%'" x-show="!fleetUploadHasFailures()"></span>
        </button>
    </div>

    <div x-show="showFleetUploadModal" data-dialog="showFleetUploadModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4"
         role="dialog" aria-modal="true" aria-labelledby="fleet-upload-title">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="if (!['receiving', 'queued', 'processing'].includes(fleetUpload.status)) dismissFleetUpload()"></div>
        <div class="relative w-full max-w-lg overflow-hidden rounded-xl bg-white shadow-[0_24px_70px_rgba(15,23,42,0.28)]">
            <div class="p-6 sm:p-7">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 id="fleet-upload-title" class="text-lg font-semibold text-text">Subir y distribuir video</h3>
                        <p class="mt-2 max-w-md text-sm leading-6 text-text-light">El MP4 se guarda primero en el VPS. La conversión y distribución continúan en segundo plano sin depender de Nginx.</p>
                    </div>
                    <button type="button" @click="dismissFleetUpload()" class="rounded-lg p-2 text-text-muted transition hover:bg-surface hover:text-text" aria-label="Cerrar">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form x-ref="fleetUploadForm" action="{{ route('fleet.upload') }}" method="POST" enctype="multipart/form-data"
                      class="mt-5 space-y-5" @submit="submitFleetUpload($event)">
                    @csrf
                    <template x-for="key in selectedFleetKeys" :key="'upload-'+key"><input type="hidden" name="targets[]" :value="key"></template>

                    <div x-show="fleetUpload.status === 'idle'">
                        <label class="block cursor-pointer rounded-xl border border-dashed border-border bg-surface p-4 transition hover:border-secondary/60 hover:bg-sky-50/50 focus-within:ring-2 focus-within:ring-secondary/25">
                            <span class="sr-only">Seleccionar video MP4</span>
                            <input type="file" name="video" accept="video/mp4,.mp4" required
                                   class="block w-full text-sm text-text file:mr-3 file:rounded-lg file:border-0 file:bg-secondary file:px-3 file:py-2 file:font-semibold file:text-white">
                        </label>
                        <p class="mt-2 text-xs text-text-muted">Formato MP4 · máximo 250 MB · <span x-text="selectedFleetKeys.length"></span> equipos seleccionados</p>
                    </div>

                    <div x-show="fleetUpload.status !== 'idle'" x-cloak class="space-y-5" aria-live="polite" aria-atomic="true">
                        <div class="flex items-center gap-4">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl"
                                 :class="fleetUploadHasFailures() ? 'bg-danger/10 text-danger' : (fleetUpload.status === 'completed' ? 'bg-success/10 text-success' : 'bg-secondary/10 text-secondary')">
                                <svg x-show="!['completed', 'failed'].includes(fleetUpload.status)" class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-20" cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3"></circle><path fill="currentColor" d="M21 12a9 9 0 00-9-9v3a6 6 0 016 6h3z"></path></svg>
                                <svg x-show="fleetUpload.status === 'completed' && !fleetUploadHasFailures()" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                <svg x-show="fleetUploadHasFailures()" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="font-semibold text-text" x-text="fleetUploadTitle()"></p>
                                    <span class="font-mono text-xs tabular-nums text-text-muted" x-text="fleetUploadElapsed()"></span>
                                </div>
                                <p class="mt-1 truncate text-xs text-text-muted" x-text="fleetUpload.fileName"></p>
                            </div>
                        </div>

                        <div x-show="!fleetUploadHasFailures()">
                            <div class="mb-2 flex items-center justify-between text-xs"><span class="font-medium text-text-light" x-text="fleetUploadDescription()"></span><span class="ml-3 font-semibold tabular-nums text-secondary" x-text="fleetUpload.progress + '%'" aria-hidden="true"></span></div>
                            <div class="h-2 overflow-hidden rounded-full bg-slate-100" role="progressbar" aria-label="Progreso de distribución" :aria-valuenow="fleetUpload.progress" aria-valuemin="0" aria-valuemax="100">
                                <div class="h-full rounded-full bg-secondary transition-[width] duration-500" :class="!['completed', 'failed'].includes(fleetUpload.status) ? 'animate-pulse' : ''" :style="`width: ${fleetUpload.progress}%`"></div>
                            </div>
                        </div>

                        <div x-show="fleetUpload.error" class="rounded-lg px-3 py-2.5 text-xs leading-5"
                             :class="fleetUpload.status === 'failed' ? 'bg-danger/10 text-danger' : 'bg-warning/10 text-amber-800'" x-text="fleetUpload.error"></div>

                        <div x-show="fleetUpload.status === 'completed' && fleetUpload.result" class="grid grid-cols-3 gap-3 rounded-xl bg-surface p-4 text-center">
                            <div><div class="text-lg font-bold tabular-nums text-text" x-text="fleetUpload.result?.total ?? 0"></div><div class="text-[11px] text-text-muted">Seleccionados</div></div>
                            <div><div class="text-lg font-bold tabular-nums text-success" x-text="fleetUpload.result?.succeeded ?? 0"></div><div class="text-[11px] text-text-muted">Completados</div></div>
                            <div><div class="text-lg font-bold tabular-nums text-danger" x-text="fleetUpload.result?.failed ?? 0"></div><div class="text-[11px] text-text-muted">Fallidos</div></div>
                        </div>
                        <div x-show="fleetUploadFailures().length" class="space-y-2" role="alert">
                            <template x-for="(failure, index) in fleetUploadFailures()" :key="failure.key || index">
                                <div class="rounded-lg bg-danger/10 px-3 py-2.5 text-xs leading-5 text-danger">
                                    <p class="font-semibold" x-text="failure.name || failure.id || failure.key || 'Ventilador'"></p>
                                    <p x-text="fleetUploadFailureMessage(failure)"></p>
                                    <p class="mt-1 break-words font-mono text-[11px]" x-show="failure.error" x-text="failure.error"></p>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div class="flex flex-wrap justify-end gap-3 border-t border-border pt-4">
                        <button type="button" x-show="['receiving', 'queued', 'processing'].includes(fleetUpload.status)" @click="hideFleetUpload()" class="rounded-lg border border-border px-4 py-2.5 text-sm font-medium text-text-light transition hover:bg-surface">Ocultar y continuar</button>
                        <button type="button" x-show="fleetUpload.status === 'idle'" @click="dismissFleetUpload()" class="rounded-lg border border-border px-4 py-2.5 text-sm font-medium text-text-light transition hover:bg-surface">Cancelar</button>
                        <button type="submit" x-show="fleetUpload.status === 'idle'" class="rounded-lg bg-secondary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-secondary/90 focus:outline-none focus:ring-2 focus:ring-secondary/30">Subir y distribuir</button>
                        <button type="button" x-show="fleetUpload.status === 'failed'" @click="window.localStorage.removeItem('fleet-upload-id'); resetFleetUpload()" class="rounded-lg bg-secondary px-4 py-2.5 text-sm font-semibold text-white">Intentar de nuevo</button>
                        <button type="button" x-show="fleetUpload.status === 'completed'" @click="window.localStorage.removeItem('fleet-upload-id'); window.location.reload()" class="rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white">Cerrar y actualizar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection
