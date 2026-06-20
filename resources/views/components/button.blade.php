@props([
    'type' => 'button',
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'icon' => null,
    'disabled' => false,
])

@php
$variantClasses = match($variant) {
    'primary' => 'bg-primary text-white hover:bg-primary/90 shadow-sm shadow-primary/20 focus:ring-primary/40',
    'secondary' => 'bg-secondary text-white hover:bg-secondary/90 shadow-sm shadow-secondary/20 focus:ring-secondary/40',
    'accent' => 'bg-accent text-text hover:bg-accent/90 shadow-sm shadow-accent/20 focus:ring-accent/40',
    'danger' => 'bg-danger text-white hover:bg-danger/90 shadow-sm shadow-danger/20 focus:ring-danger/40',
    'ghost' => 'bg-transparent text-text hover:bg-surface border border-border hover:border-border focus:ring-border/40',
    default => 'bg-primary text-white hover:bg-primary/90',
};

$sizeClasses = match($size) {
    'sm' => 'px-3 py-1.5 text-xs',
    'lg' => 'px-6 py-3 text-base',
    default => 'px-4 py-2 text-sm',
};

$iconSizes = match($size) {
    'sm' => 'w-3.5 h-3.5',
    'lg' => 'w-5 h-5',
    default => 'w-4 h-4',
};

$baseClasses = 'inline-flex items-center justify-center gap-2 font-medium rounded-lg transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed';
@endphp

@if ($href)
    <a href="{{ $href }}" class="{{ $baseClasses }} {{ $variantClasses }} {{ $sizeClasses }} {{ $disabled ? 'opacity-50 cursor-not-allowed pointer-events-none' : '' }}">
        @if ($icon)
            <svg class="{{ $iconSizes }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $icon }}"></path>
            </svg>
        @endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $disabled ? 'disabled' : '' }} class="{{ $baseClasses }} {{ $variantClasses }} {{ $sizeClasses }}">
        @if ($icon)
            <svg class="{{ $iconSizes }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $icon }}"></path>
            </svg>
        @endif
        {{ $slot }}
    </button>
@endif
