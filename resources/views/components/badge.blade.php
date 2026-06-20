@props([
    'color' => 'primary',
    'label',
])

@php
$colorClasses = match($color) {
    'success' => 'bg-success/10 text-success border border-success/20',
    'warning' => 'bg-warning/10 text-warning border border-warning/20',
    'danger' => 'bg-danger/10 text-danger border border-danger/20',
    'info' => 'bg-info/10 text-info border border-info/20',
    'primary' => 'bg-primary/10 text-primary border border-primary/20',
    'secondary' => 'bg-secondary/10 text-secondary border border-secondary/20',
    default => 'bg-surface text-text border border-border',
};

$dotClasses = match($color) {
    'success' => 'bg-success',
    'warning' => 'bg-warning',
    'danger' => 'bg-danger',
    'info' => 'bg-info',
    'primary' => 'bg-primary',
    'secondary' => 'bg-secondary',
    default => 'bg-text-muted',
};
@endphp

<span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium {{ $colorClasses }}">
    <span class="w-1.5 h-1.5 rounded-full {{ $dotClasses }}"></span>
    {{ $label }}
</span>
