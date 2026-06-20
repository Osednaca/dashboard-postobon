@props([
    'type' => 'info',
    'message',
    'duration' => 4000,
])

@php
$colorClasses = match($type) {
    'success' => 'bg-success text-white',
    'error' => 'bg-danger text-white',
    'warning' => 'bg-warning text-white',
    default => 'bg-text text-white',
};

$iconPaths = match($type) {
    'success' => 'M5 13l4 4L19 7',
    'error' => 'M6 18L18 6M6 6l12 12',
    'warning' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
    default => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
};
@endphp

<div
    x-data="{ show: false, message: '', init() { this.show = true; setTimeout(() => this.show = false, {{ $duration }}); } }"
    x-show="show"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0 translate-y-4"
    x-transition:enter-end="opacity-100 translate-y-0"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100 translate-y-0"
    x-transition:leave-end="opacity-0 translate-y-4"
    class="fixed bottom-6 right-6 z-50 flex items-center gap-3 px-4 py-3 rounded-xl shadow-lg {{ $colorClasses }} max-w-sm"
    role="alert"
>
    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $iconPaths }}"></path>
    </svg>
    <p class="text-sm font-medium">{{ $message }}</p>
    <button @click="show = false" class="shrink-0 hover:opacity-70 transition-opacity ml-1">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
    </button>
</div>
