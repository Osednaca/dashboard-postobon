@props([
    'progress' => 0,
    'color' => 'primary',
    'height' => '8',
    'striped' => false,
])

@php
$colorClasses = match($color) {
    'secondary' => 'bg-secondary',
    'accent' => 'bg-accent',
    'success' => 'bg-success',
    'warning' => 'bg-warning',
    'danger' => 'bg-danger',
    default => 'bg-primary',
};
@endphp

<div class="w-full">
    <div class="w-full bg-surface rounded-full overflow-hidden" style="height: {{ $height }}px;">
        <div
            class="h-full rounded-full {{ $colorClasses }} transition-all duration-500 ease-out {{ $striped ? 'bg-stripes' : '' }}"
            style="width: {{ min(max($progress, 0), 100) }}%;"
        ></div>
    </div>
    @if ($progress > 0)
        <div class="flex justify-between mt-1">
            <span class="text-xs text-text-muted">{{ min(max($progress, 0), 100) }}%</span>
        </div>
    @endif
</div>

<style>
    .bg-stripes {
        background-image: linear-gradient(
            45deg,
            rgba(255, 255, 255, 0.15) 25%,
            transparent 25%,
            transparent 50%,
            rgba(255, 255, 255, 0.15) 50%,
            rgba(255, 255, 255, 0.15) 75%,
            transparent 75%,
            transparent
        );
        background-size: 1rem 1rem;
    }
</style>
