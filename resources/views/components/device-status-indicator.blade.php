@props([
    'status' => 'offline',
])

@php
$colorClasses = match($status) {
    'online' => 'bg-success',
    'offline' => 'bg-danger',
    'disabled' => 'bg-text-muted',
    'maintenance' => 'bg-warning',
    default => 'bg-text-muted',
};

$labels = [
    'online' => 'En línea',
    'offline' => 'Fuera de línea',
    'disabled' => 'Deshabilitado',
    'maintenance' => 'En mantenimiento',
];
@endphp

<div class="flex items-center gap-2">
    <span class="relative flex h-2.5 w-2.5">
        @if ($status === 'online')
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full {{ $colorClasses }} opacity-75"></span>
        @endif
        <span class="relative inline-flex rounded-full h-2.5 w-2.5 {{ $colorClasses }}"></span>
    </span>
    <span class="text-xs text-text-muted">{{ $labels[$status] ?? $status }}</span>
</div>
