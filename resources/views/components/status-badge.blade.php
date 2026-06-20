@props(['status', 'size' => 'md'])

@php
$styles = [
    'draft' => 'bg-gray-100 text-gray-700 border-gray-200',
    'scheduled' => 'bg-info/10 text-info border-info/20',
    'active' => 'bg-success/10 text-success border-success/20',
    'paused' => 'bg-warning/10 text-warning border-warning/20',
    'finished' => 'bg-text-muted/10 text-text-muted border-text-muted/20',
    'pending' => 'bg-warning/10 text-warning border-warning/20',
    'executed' => 'bg-success/10 text-success border-success/20',
    'failed' => 'bg-danger/10 text-danger border-danger/20',
    'suspended' => 'bg-warning/10 text-warning border-warning/20',
    'expired' => 'bg-danger/10 text-danger border-danger/20',
    'unread' => 'bg-primary/10 text-primary border-primary/20',
    'read' => 'bg-gray-100 text-gray-700 border-gray-200',
];

$labels = [
    'draft' => 'Borrador',
    'scheduled' => 'Programada',
    'active' => 'Activa',
    'paused' => 'Pausada',
    'finished' => 'Finalizada',
    'pending' => 'Pendiente',
    'executed' => 'Ejecutada',
    'failed' => 'Fallida',
    'suspended' => 'Suspendida',
    'expired' => 'Expirada',
    'unread' => 'No leída',
    'read' => 'Leída',
];

$style = $styles[$status] ?? 'bg-gray-100 text-gray-700 border-gray-200';
$label = $labels[$status] ?? ucfirst($status);
$sizes = ['sm' => 'px-2 py-0.5 text-xs', 'md' => 'px-2.5 py-1 text-xs', 'lg' => 'px-3 py-1.5 text-sm'];
$sizeClass = $sizes[$size] ?? $sizes['md'];
@endphp

<span class="inline-flex items-center rounded-full border {{ $sizeClass }} font-medium {{ $style }}">
    {{ $label }}
</span>
