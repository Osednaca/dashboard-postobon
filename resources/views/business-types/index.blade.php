@extends('layouts.app')

@section('title', 'Tipos de negocio')

@section('content')
<div class="mx-auto max-w-5xl space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-text">Tipos de negocio</h1>
            <p class="mt-1 text-sm text-text-light">Define las categorías disponibles para los establecimientos.</p>
        </div>
        <a href="{{ route('establishments.index') }}" class="inline-flex items-center justify-center rounded-lg border border-border bg-white px-4 py-2.5 text-sm font-medium text-text transition hover:bg-surface">Volver a establecimientos</a>
    </div>

    @if($errors->any())
        <div class="rounded-lg border border-danger/20 bg-danger/10 px-4 py-3 text-sm text-danger" role="alert">
            {{ $errors->first() }}
        </div>
    @endif

    <section class="rounded-xl border border-border bg-white p-5 shadow-[0_1px_3px_rgba(0,0,0,0.04)] sm:p-6">
        <h2 class="text-lg font-semibold text-text">Agregar tipo</h2>
        <form action="{{ route('business-types.store') }}" method="POST" class="mt-4 flex flex-col gap-3 sm:flex-row">
            @csrf
            <label class="min-w-0 flex-1">
                <span class="sr-only">Nombre del tipo de negocio</span>
                <input name="name" value="{{ old('name') }}" required maxlength="120" autocomplete="off" placeholder="Ej. Hotel, supermercado o restaurante" class="w-full rounded-lg border border-border px-4 py-2.5 text-base outline-none transition placeholder:text-text-muted focus:border-primary focus:ring-2 focus:ring-primary/20 sm:text-sm">
            </label>
            <button class="rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-primary/20 transition hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-primary/30 disabled:opacity-50">Agregar tipo</button>
        </form>
    </section>

    <section class="overflow-hidden rounded-xl border border-border bg-white shadow-[0_1px_3px_rgba(0,0,0,0.04)]">
        @forelse($businessTypes as $businessType)
            <div class="flex flex-col gap-3 border-b border-border px-5 py-4 last:border-b-0 sm:flex-row sm:items-center">
                <form action="{{ route('business-types.update', $businessType) }}" method="POST" class="flex min-w-0 flex-1 flex-col gap-3 sm:flex-row sm:items-center">
                    @csrf
                    @method('PUT')
                    <input name="name" value="{{ $businessType->name }}" required maxlength="120" aria-label="Nombre de {{ $businessType->name }}" class="min-w-0 flex-1 rounded-lg border border-transparent bg-surface px-3 py-2 text-base font-medium text-text outline-none transition focus:border-primary focus:bg-white focus:ring-2 focus:ring-primary/20 sm:text-sm">
                    <span class="shrink-0 text-xs text-text-muted">{{ $businessType->establishments_count }} establecimientos</span>
                    <button class="rounded-lg border border-border px-3 py-2 text-sm font-medium text-text transition hover:bg-surface">Guardar</button>
                </form>
                <form action="{{ route('business-types.destroy', $businessType) }}" method="POST" onsubmit="return confirm('¿Eliminar este tipo de negocio?')">
                    @csrf
                    @method('DELETE')
                    <button class="rounded-lg px-3 py-2 text-sm font-medium text-danger transition hover:bg-danger/10 disabled:cursor-not-allowed disabled:opacity-40" @disabled($businessType->establishments_count > 0) title="{{ $businessType->establishments_count > 0 ? 'Tiene establecimientos asociados' : 'Eliminar tipo' }}">Eliminar</button>
                </form>
            </div>
        @empty
            <div class="px-6 py-12 text-center">
                <p class="font-semibold text-text">No hay tipos de negocio</p>
                <p class="mt-1 text-sm text-text-light">Agrega el primero para poder registrar establecimientos.</p>
            </div>
        @endforelse

        @if($businessTypes->hasPages())
            <div class="border-t border-border px-5 py-4">{{ $businessTypes->links() }}</div>
        @endif
    </section>
</div>
@endsection
