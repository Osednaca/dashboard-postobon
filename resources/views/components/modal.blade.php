@props(['id', 'title', 'triggerText' => null, 'triggerIcon' => null, 'triggerColor' => 'primary'])

@php
$triggerColors = [
    'primary' => 'bg-primary text-white hover:bg-primary/90',
    'danger' => 'bg-danger text-white hover:bg-danger/90',
    'default' => 'bg-white text-text border border-border hover:bg-surface-dark',
];
$triggerColorClass = $triggerColors[$triggerColor] ?? $triggerColors['primary'];
@endphp

<div x-data="{ open: false }" class="inline-block">
    @if($triggerText || $triggerIcon)
        <button @click="open = true" type="button" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors {{ $triggerColorClass }}">
            @if($triggerIcon)
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $triggerIcon }}"></path>
                </svg>
            @endif
            {{ $triggerText }}
        </button>
    @endif

    <div x-show="open" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center p-4" @keydown.escape.window="open = false">
        <div class="fixed inset-0 bg-black/50 transition-opacity" @click="open = false"></div>
        <div class="relative bg-white rounded-xl shadow-xl border border-border w-full max-w-lg overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b border-border">
                <h3 class="text-lg font-semibold text-text">{{ $title }}</h3>
                <button @click="open = false" class="p-1 rounded-lg hover:bg-surface-dark text-text-muted">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div class="px-6 py-4">
                {{ $slot }}
            </div>
        </div>
    </div>
</div>
