@props([
    'title' => 'Sin datos',
    'description' => 'No hay elementos para mostrar en este momento.',
    'icon' => 'inbox',
    'action' => null,
])

@php
$icons = [
    'inbox' => 'M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4',
    'folder' => 'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z',
    'search' => 'M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0',
    'file' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
    'box' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
];
@endphp

<div class="flex flex-col items-center justify-center py-16 px-4 text-center">
    <div class="w-16 h-16 rounded-2xl bg-surface border border-border flex items-center justify-center mb-4">
        @if (isset($icons[$icon]))
            <svg class="w-8 h-8 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="{{ $icons[$icon] }}"></path>
            </svg>
        @endif
    </div>
    <h3 class="text-lg font-semibold text-text mb-1">{{ $title }}</h3>
    <p class="text-sm text-text-muted max-w-sm">{{ $description }}</p>
    @if ($action)
        <div class="mt-4">
            {{ $action }}
        </div>
    @endif
</div>
