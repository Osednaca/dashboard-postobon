@extends('layouts.app')

@section('title', 'Editar establecimiento')

@section('content')
<div class="mx-auto max-w-4xl space-y-6">
    <div><a href="{{ route('establishments.index') }}" class="text-sm font-medium text-primary hover:underline">← Establecimientos</a><h1 class="mt-3 break-words text-2xl font-bold text-text">Editar {{ $establishment->name }}</h1><p class="mt-1 text-sm text-text-light">Los cambios se reflejarán en todos los dispositivos asociados.</p></div>
    <form action="{{ route('establishments.update', $establishment) }}" method="POST" class="space-y-6 rounded-xl border border-border bg-white p-6 shadow-[0_1px_3px_rgba(0,0,0,0.04)] sm:p-8">
        @csrf
        @method('PUT')
        @include('establishments._form')
        <div class="flex flex-col-reverse gap-3 border-t border-border pt-6 sm:flex-row sm:justify-end"><a href="{{ route('establishments.index') }}" class="rounded-lg border border-border px-4 py-2.5 text-center text-sm font-medium text-text transition hover:bg-surface">Cancelar</a><button class="rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-primary/20 transition hover:bg-primary/90">Guardar cambios</button></div>
    </form>
</div>
@endsection
