<x-app-layout>
    <x-slot name="title">Nueva Programación</x-slot>

    <div class="mx-auto max-w-3xl space-y-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('schedules.index') }}" class="rounded-lg p-2 text-text-light transition-colors hover:bg-surface-dark" aria-label="Volver a programaciones">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
            </a>
            <div>
                <h2 class="text-2xl font-bold text-text">Nueva programación</h2>
                <p class="mt-1 text-sm text-text-muted">Crea una instrucción única o recurrente para tus dispositivos.</p>
            </div>
        </div>

        <form action="{{ route('schedules.store') }}" method="POST" class="overflow-hidden rounded-2xl border border-border bg-white shadow-[0_1px_3px_rgba(0,0,0,0.08),0_12px_32px_rgba(45,87,149,0.06)]">
            @csrf
            @include('schedules._form')

            <div class="flex items-center justify-end gap-3 border-t border-border bg-surface px-6 py-4 sm:px-8">
                <a href="{{ route('schedules.index') }}" class="rounded-lg px-4 py-2.5 text-sm font-medium text-text-light transition-colors hover:bg-surface-dark">Cancelar</a>
                <button type="submit" class="rounded-lg bg-primary px-6 py-2.5 text-sm font-medium text-white shadow-sm transition-colors hover:bg-primary/90">Crear programación</button>
            </div>
        </form>
    </div>
</x-app-layout>
