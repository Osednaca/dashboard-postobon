@props([
    'title',
    'value',
    'change' => null,
    'changeType' => 'increase',
    'icon',
    'color' => 'primary',
])

@php
$colorClasses = match($color) {
    'secondary' => 'bg-secondary/10 text-secondary',
    'accent' => 'bg-accent/10 text-accent',
    'success' => 'bg-success/10 text-success',
    'warning' => 'bg-warning/10 text-warning',
    default => 'bg-primary/10 text-primary',
};

$icons = [
    'trending-up' => 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6',
    'trending-down' => 'M13 17h8m0 0V9m0 8l-8-8-4 4-6-6',
    'users' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z',
    'cpu' => 'M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z',
    'eye' => 'M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z',
    'clock' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
    'play' => 'M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    'check' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
];

$trendIcon = $changeType === 'increase' ? 'trending-up' : 'trending-down';
$trendColor = $changeType === 'increase' ? 'text-success' : 'text-danger';
@endphp

<div class="bg-white rounded-xl border border-border p-6 shadow-sm hover:shadow-md transition-shadow duration-200">
    <div class="flex items-center justify-between mb-4">
        <div class="w-10 h-10 rounded-lg {{ $colorClasses }} flex items-center justify-center">
            @if (isset($icons[$icon]))
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $icons[$icon] }}"></path>
                </svg>
            @endif
        </div>
        @if ($change !== null)
            <div class="flex items-center gap-1 text-sm {{ $trendColor }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $icons[$trendIcon] }}"></path>
                </svg>
                <span class="font-medium">{{ $change }}</span>
            </div>
        @endif
    </div>
    <h3 class="text-sm font-medium text-text-muted mb-1">{{ $title }}</h3>
    <p class="text-2xl font-bold text-text tracking-tight">{{ $value }}</p>
</div>
