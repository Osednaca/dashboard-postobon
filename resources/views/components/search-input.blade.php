@props([
    'name',
    'placeholder' => 'Buscar...',
    'value' => null,
])

<div class="relative w-full max-w-xs">
    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
        <svg class="w-4 h-4 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"></path>
        </svg>
    </div>
    <input
        type="search"
        name="{{ $name }}"
        id="{{ $name }}"
        value="{{ $value }}"
        placeholder="{{ $placeholder }}"
        class="w-full rounded-lg border border-border bg-surface text-text placeholder:text-text-muted focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none transition-colors duration-150 pl-9 pr-4 py-2 text-sm"
    />
</div>
