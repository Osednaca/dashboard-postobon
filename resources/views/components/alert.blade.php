@props([
    'type' => 'info',
    'message',
    'dismissible' => true,
])

@php
$colorClasses = match($type) {
    'success' => 'bg-success/10 border-success/20 text-success',
    'error' => 'bg-danger/10 border-danger/20 text-danger',
    'warning' => 'bg-warning/10 border-warning/20 text-warning',
    default => 'bg-info/10 border-info/20 text-info',
};

$iconPaths = match($type) {
    'success' => 'M5 13l4 4L19 7',
    'error' => 'M6 18L18 6M6 6l12 12',
    'warning' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
    default => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
};
@endphp

<div
    x-data="{ show: true, timeout: null }"
    x-show="show"
    x-init="timeout = setTimeout(() => { if (!{{ $dismissible ? 'false' : 'true' }}) show = false }, 6000)"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0 translate-y-2"
    x-transition:enter-end="opacity-100 translate-y-0"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100 translate-y-0"
    x-transition:leave-end="opacity-0 translate-y-2"
    class="rounded-lg border px-4 py-3 flex items-start gap-3 {{ $colorClasses }}"
    role="alert"
>
    <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $iconPaths }}"></path>
    </svg>

    <div class="flex-1 text-sm">
        {{ $message }}
    </div>

    @if ($dismissible)
        <button @click="show = false; clearTimeout(timeout)" class="shrink-0 hover:opacity-70 transition-opacity">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
    @endif
</div>
