@extends('layouts.app')

@section('title', 'Nuevo establecimiento')

@section('content')
<div class="mx-auto max-w-4xl space-y-6">
    <div><a href="{{ route('establishments.index') }}" class="text-sm font-medium text-primary hover:underline">← Establecimientos</a><h1 class="mt-3 text-2xl font-bold text-text">Nuevo establecimiento</h1><p class="mt-1 text-sm text-text-light">Estos datos se reutilizarán al registrar cualquier ventilador.</p></div>
    <form action="{{ route('establishments.store') }}" method="POST" class="space-y-6 rounded-xl border border-border bg-white p-6 shadow-[0_1px_3px_rgba(0,0,0,0.04)] sm:p-8">
        @csrf
        @include('establishments._form')
        <div class="flex flex-col-reverse gap-3 border-t border-border pt-6 sm:flex-row sm:justify-end"><a href="{{ route('establishments.index') }}" class="rounded-lg border border-border px-4 py-2.5 text-center text-sm font-medium text-text transition hover:bg-surface">Cancelar</a><button @disabled($businessTypes->isEmpty()) class="rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-primary/20 transition hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-50">Guardar establecimiento</button></div>
    </form>
</div>
@endsection
