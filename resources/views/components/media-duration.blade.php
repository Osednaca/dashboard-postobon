@props(['media'])
@php
    $duration = max(0, (int) $media->duration);
    $label = $duration >= 3600
        ? sprintf('%d:%02d:%02d', intdiv($duration, 3600), intdiv($duration % 3600, 60), $duration % 60)
        : ($duration ? sprintf('%02d:%02d', intdiv($duration, 60), $duration % 60) : '—');
@endphp
<span x-text="$store.mediaDurations.label(@js((string) $media->id), @js($duration))">{{ $label }}</span>
