@extends('layouts.app')

@section('title', 'Editar dispositivo')

@section('content')
<div class="mx-auto max-w-3xl space-y-6">
    <div>
        <a href="{{ route('devices.index') }}" class="text-sm font-medium text-primary hover:underline">← Dispositivos</a>
        <h1 class="mt-3 break-words text-2xl font-bold text-text">Editar {{ $device->name }}</h1>
        <p class="mt-1 text-sm text-text-light">Actualiza la identidad del Z2 o cambia el establecimiento asociado.</p>
    </div>

    <form action="{{ route('devices.update', $device) }}" method="POST" class="space-y-6 rounded-xl border border-border bg-white p-6 shadow-[0_1px_3px_rgba(0,0,0,0.04)] sm:p-8">
        @csrf
        @method('PUT')

        @if($errors->any())
            <div class="rounded-lg border border-danger/20 bg-danger/10 px-4 py-3 text-sm text-danger" role="alert"><p class="font-semibold">Revisa los datos:</p><ul class="mt-2 list-disc space-y-1 pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif

        <div class="grid gap-6 sm:grid-cols-2">
            <div class="sm:col-span-2"><label for="name" class="mb-2 block text-sm font-medium text-text">Nombre <span class="text-danger">*</span></label><input id="name" name="name" value="{{ old('name', $device->name) }}" required maxlength="255" class="w-full rounded-lg border border-border px-4 py-2.5 text-base outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20 sm:text-sm"></div>
            <div class="sm:col-span-2"><label for="mac_address" class="mb-2 block text-sm font-medium text-text">Dirección MAC <span class="text-danger">*</span></label><input id="mac_address" name="mac_address" value="{{ old('mac_address', $device->mac_address) }}" required maxlength="255" spellcheck="false" class="w-full rounded-lg border border-border px-4 py-2.5 font-mono text-base outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20 sm:text-sm"></div>
            <div><label for="firmware" class="mb-2 block text-sm font-medium text-text">Firmware</label><input id="firmware" name="firmware" value="{{ old('firmware', $device->firmware) }}" maxlength="255" class="w-full rounded-lg border border-border px-4 py-2.5 text-base outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20 sm:text-sm"></div>
            <div><label for="hardware" class="mb-2 block text-sm font-medium text-text">Hardware</label><input id="hardware" name="hardware" value="{{ old('hardware', $device->hardware) }}" maxlength="255" class="w-full rounded-lg border border-border px-4 py-2.5 text-base outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20 sm:text-sm"></div>
            <div class="sm:col-span-2"><div class="mb-2 flex items-center justify-between gap-3"><label for="establishment_id" class="text-sm font-medium text-text">Establecimiento <span class="text-danger">*</span></label><a href="{{ route('establishments.create') }}" class="text-xs font-semibold text-primary hover:underline">Crear nuevo</a></div><select id="establishment_id" name="establishment_id" required class="w-full rounded-lg border border-border bg-white px-4 py-2.5 text-base outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 sm:text-sm"><option value="">Seleccionar establecimiento</option>@foreach($establishments as $establishment)<option value="{{ $establishment->id }}" @selected((string) old('establishment_id', $device->establishment_id) === (string) $establishment->id)>{{ $establishment->name }} · {{ $establishment->businessType?->name }} — {{ $establishment->address }}</option>@endforeach</select><p class="mt-1.5 text-xs text-text-muted">Dirección, contacto y Wi‑Fi se administran desde el establecimiento.</p></div>
        </div>

        <div class="rounded-lg bg-surface px-4 py-3 text-sm text-text-light"><span class="font-medium text-text">Estado actual:</span> {{ $device->status ?? 'desconocido' }} · Energía {{ $device->power_status ?? 'desconocida' }} · Último reporte {{ $device->last_heartbeat_at?->format('d/m/Y H:i') ?? 'nunca' }}</div>

        <div class="flex flex-col-reverse gap-3 border-t border-border pt-6 sm:flex-row sm:justify-end"><a href="{{ route('devices.index') }}" class="rounded-lg border border-border px-4 py-2.5 text-center text-sm font-medium text-text transition hover:bg-surface">Cancelar</a><button class="rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-primary/20 transition hover:bg-primary/90">Guardar cambios</button></div>
    </form>
</div>
@endsection
