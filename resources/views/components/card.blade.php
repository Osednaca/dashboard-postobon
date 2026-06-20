@props(['title' => null, 'subtitle' => null, 'color' => 'default'])

@php
$colorClasses = match($color) {
    'primary' => 'border-l-4 border-l-primary',
    'secondary' => 'border-l-4 border-l-secondary',
    'accent' => 'border-l-4 border-l-accent',
    default => 'border border-border',
};
@endphp

<div class="bg-white rounded-xl shadow-sm hover:shadow-md transition-shadow duration-200 {{ $colorClasses }}">
    @if ($title || $subtitle || isset($header))
        <div class="px-6 py-4 border-b border-border">
            @if (isset($header))
                {{ $header }}
            @else
                @if ($title)
                    <h3 class="text-base font-semibold text-text">{{ $title }}</h3>
                @endif
                @if ($subtitle)
                    <p class="text-sm text-text-muted mt-0.5">{{ $subtitle }}</p>
                @endif
            @endif
        </div>
    @endif

    <div class="px-6 py-4">
        {{ $slot }}
    </div>

    @if (isset($footer))
        <div class="px-6 py-3 border-t border-border bg-surface/50 rounded-b-xl">
            {{ $footer }}
        </div>
    @endif
</div>
