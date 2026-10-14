@props(['name'])
@php
    $paths = [
        'monitor' => 'M4 4h16v12H4zM9 20h6m-3-4v4',
        'power' => 'M12 3v9m-5.5-7a8 8 0 1 0 11 0',
        'bluetooth' => 'M7 7l10 10-5 4V3l5 4L7 17',
        'play' => 'm9 5 11 7-11 7V5Z',
        'more' => 'M5 12h.01M12 12h.01M19 12h.01',
        'info' => 'M12 11v6m0-10h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
        'edit' => 'm16 3 5 5-12 12H4v-5L16 3Zm-3 3 5 5',
        'trash' => 'M4 7h16M9 7V4h6v3M6 7l1 14h10l1-14M10 11v6m4-6v6',
        'card' => 'M7 3h10l3 4v14H4V6l3-3ZM8 3v5h8V3M8 13h8m-8 4h8',
        'refresh' => 'M20 7v5h-5M4 17v-5h5m-4-4a8 8 0 0 1 14-2l1 2M4 16l1 2a8 8 0 0 0 14-2',
        'plus' => 'M12 5v14M5 12h14',
        'close' => 'm6 6 12 12M6 18 18 6',
        'dot' => 'M16 12a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z',
    ];
@endphp
<svg {{ $attributes->class('device-action-icon') }} viewBox="0 0 24 24" fill="{{ $name === 'dot' ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $paths[$name] ?? $paths['monitor'] }}" /></svg>
