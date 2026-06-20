@props([
    'status' => 'draft',
])

@php
$colorClasses = match($status) {
    'draft' => 'bg-text-muted/10 text-text-muted border-text-muted/20',
    'scheduled' => 'bg-info/10 text-info border-info/20',
    'active' => 'bg-success/10 text-success border-success/20',
    'paused' => 'bg-warning/10 text-warning border-warning/20',
    'finished' => 'bg-primary/10 text-primary border-primary/20',
    default => 'bg-surface text-text border-border',
};

$labels = [
    'draft' => 'Borrador',
    'scheduled' => 'Programada',
    'active' => 'Activa',
    'paused' => 'Pausada',
    'finished' => 'Finalizada',
];
@endphp

<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $colorClasses }}">
    {{ $labels[$status] ?? $status }}
</span>
