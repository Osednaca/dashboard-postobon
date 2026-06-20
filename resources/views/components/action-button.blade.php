@props(['icon', 'label', 'color' => 'default', 'href' => null, 'confirm' => null, 'method' => 'GET'])

@php
$colors = [
    'default' => 'text-text-light hover:bg-surface-dark hover:text-text',
    'primary' => 'text-primary hover:bg-primary/10',
    'danger' => 'text-danger hover:bg-danger/10',
    'success' => 'text-success hover:bg-success/10',
    'warning' => 'text-warning hover:bg-warning/10',
    'info' => 'text-info hover:bg-info/10',
];
$colorClass = $colors[$color] ?? $colors['default'];
$confirmMessage = "¿Está seguro de que desea " . strtolower($label) . "?";
@endphp

@if($href)
    @if($method !== 'GET')
        <form method="POST" action="{{ $href }}" class="inline" @if($confirm) onsubmit="return confirm('{{ $confirmMessage }}');" @endif>
            @csrf
            @method($method)
            <button type="submit" class="p-1.5 rounded-lg transition-colors {{ $colorClass }}" title="{{ $label }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $icon }}"></path>
                </svg>
            </button>
        </form>
    @else
        @if($confirm)
            <form method="POST" action="{{ $href }}" class="inline" onsubmit="return confirm('{{ $confirmMessage }}');">
                @csrf
                <button type="submit" class="p-1.5 rounded-lg transition-colors {{ $colorClass }}" title="{{ $label }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $icon }}"></path>
                    </svg>
                </button>
            </form>
        @else
            <a href="{{ $href }}" class="inline-flex items-center p-1.5 rounded-lg transition-colors {{ $colorClass }}" title="{{ $label }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $icon }}"></path>
                </svg>
            </a>
        @endif
    @endif
@else
    <button type="button" class="p-1.5 rounded-lg transition-colors {{ $colorClass }}" title="{{ $label }}">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $icon }}"></path>
        </svg>
    </button>
@endif
