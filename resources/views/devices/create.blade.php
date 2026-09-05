@extends('layouts.app')

@section('title', 'Nuevo dispositivo Z2')

@section('content')
<div class="mx-auto max-w-3xl space-y-6">
    <div>
        <a href="{{ route('devices.index') }}" class="text-sm font-medium text-primary hover:underline">← Dispositivos</a>
        <h1 class="mt-3 text-2xl font-bold text-text">Nuevo dispositivo Z2</h1>
        <p class="mt-1 text-sm text-text-light">Registra la identidad del equipo y asígnalo a un establecimiento existente.</p>
    </div>

    @if($establishments->isEmpty())
        <div class="rounded-xl border border-warning/30 bg-warning/10 px-5 py-4 text-sm text-amber-900" role="alert">
            <p class="font-semibold">Primero debes crear un establecimiento.</p>
            <p class="mt-1">La dirección, contacto y red Wi‑Fi se administran una sola vez desde ese registro.</p>
            <a href="{{ route('establishments.create') }}" class="mt-3 inline-flex font-semibold text-primary hover:underline">Crear establecimiento</a>
        </div>
    @endif

    <form action="{{ route('devices.store') }}" method="POST" class="space-y-6 rounded-xl border border-border bg-white p-6 shadow-[0_1px_3px_rgba(0,0,0,0.04)] sm:p-8">
        @csrf

        @if($errors->any())
            <div class="rounded-lg border border-danger/20 bg-danger/10 px-4 py-3 text-sm text-danger" role="alert"><p class="font-semibold">Revisa los datos:</p><ul class="mt-2 list-disc space-y-1 pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif

        <div>
            <label for="name" class="mb-2 block text-sm font-medium text-text">Nombre del dispositivo <span class="text-danger">*</span></label>
            <input id="name" name="name" value="{{ old('name') }}" required maxlength="255" autocomplete="off" class="w-full rounded-lg border border-border px-4 py-2.5 text-base outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20 sm:text-sm">
        </div>

        <div>
            <label for="mac_address" class="mb-2 block text-sm font-medium text-text">Dirección MAC <span class="text-danger">*</span></label>
            <input id="mac_address" name="mac_address" value="{{ old('mac_address') }}" required maxlength="255" autocomplete="off" spellcheck="false" placeholder="00:1A:2B:3C:4D:5E" class="w-full rounded-lg border border-border px-4 py-2.5 font-mono text-base outline-none transition placeholder:text-text-muted focus:border-primary focus:ring-2 focus:ring-primary/20 sm:text-sm">
        </div>

        <div>
            <div class="mb-2 flex items-center justify-between gap-3"><label for="establishment_id" class="text-sm font-medium text-text">Establecimiento <span class="text-danger">*</span></label><a href="{{ route('establishments.create') }}" class="text-xs font-semibold text-primary hover:underline">Crear nuevo</a></div>
            <select id="establishment_id" name="establishment_id" required class="w-full rounded-lg border border-border bg-white px-4 py-2.5 text-base outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20 sm:text-sm">
                <option value="">Seleccionar establecimiento</option>
                @foreach($establishments as $establishment)<option value="{{ $establishment->id }}" @selected((string) old('establishment_id') === (string) $establishment->id)>{{ $establishment->name }} · {{ $establishment->businessType?->name }} — {{ $establishment->address }}</option>@endforeach
            </select>
            <p class="mt-1.5 text-xs leading-5 text-text-muted">El contacto, la ubicación del mapa y las credenciales Wi‑Fi se tomarán del establecimiento.</p>
        </div>

        <div class="flex flex-col-reverse gap-3 border-t border-border pt-6 sm:flex-row sm:justify-end">
            <a href="{{ route('devices.index') }}" class="rounded-lg border border-border px-4 py-2.5 text-center text-sm font-medium text-text transition hover:bg-surface">Cancelar</a>
            <button @disabled($establishments->isEmpty()) class="rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-primary/20 transition hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-50">Guardar dispositivo</button>
        </div>
    </form>
</div>
@endsection
